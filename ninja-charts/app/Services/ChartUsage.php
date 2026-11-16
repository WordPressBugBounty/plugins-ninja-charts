<?php

namespace NinjaCharts\App\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Finds the posts that show a saved chart through its shortcode, for the "Used on" column
 * of the admin chart list.
 *
 * Only the shortcode references a saved chart: [ninja_charts id="90"], in any post type a
 * user can edit (posts, pages, synced patterns, custom types), whether it was typed into the
 * classic editor, added with the TinyMCE button or put in a Shortcode block. The Gutenberg
 * "Ninja Charts" block keeps its own labels, datasets and options in its attributes and has
 * no chart id, so it never uses a saved chart. Shortcodes in widgets live in wp_options, not
 * in posts, and are not looked up.
 */
class ChartUsage
{
    /** Most chart ids one request may ask about (a page of the chart list is at most 100 rows). */
    const MAX_IDS = 100;

    /** Most posts listed for one chart. The total still counts every post. */
    const MAX_POSTS_PER_CHART = 10;

    /** Posts read from the database per query, so a large site never loads every post at once. */
    const CHUNK_SIZE = 200;

    /** Statuses of content that exists for the user: no revisions, auto-drafts or trash. */
    const STATUSES = ['publish', 'future', 'draft', 'pending', 'private'];

    /**
     * Every shortcode on the site, before anyone's permissions are applied. One entry for the
     * whole site, the same for every user, so links and permissions are never cached.
     */
    const CACHE_KEY = 'ninja_charts_usage_map';
    const CACHE_TTL = 6 * HOUR_IN_SECONDS;

    /**
     * Turns "90,86,85" or [90, '86'] into unique positive ids, at most MAX_IDS of them.
     *
     * @param mixed $raw
     * @return int[]
     */
    public static function parseIds($raw)
    {
        if (is_string($raw)) {
            $raw = explode(',', $raw);
        }
        if (!is_array($raw)) {
            return [];
        }

        $ids = [];
        foreach ($raw as $value) {
            if (!is_scalar($value)) {
                continue;
            }
            $id = intval($value);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_slice(array_values($ids), 0, self::MAX_IDS);
    }

    /**
     * Usage of each chart, keyed by chart id:
     * [ 90 => ['total' => 12, 'items' => [ [id, title, type_label, status, edit_url, view_url], ... ]] ].
     * Only posts the current user may read are counted.
     *
     * @param int[] $chartIds
     * @return array
     */
    public static function forCharts(array $chartIds)
    {
        $chartIds = self::parseIds($chartIds);
        if (!$chartIds) {
            return [];
        }

        $map   = self::candidates();
        $usage = [];

        // read_post and the links each load their post: one query for all of them up front
        $postIds = [];
        foreach ($chartIds as $chartId) {
            foreach (isset($map[$chartId]) ? $map[$chartId] : [] as $post) {
                $postIds[$post['id']] = $post['id'];
            }
        }
        if ($postIds) {
            _prime_post_caches(array_values($postIds), false, false);
        }

        foreach ($chartIds as $chartId) {
            $usage[$chartId] = ['total' => 0, 'items' => []];
            $posts = isset($map[$chartId]) ? $map[$chartId] : [];

            foreach ($posts as $post) {
                // The chart list is open to whatever role ninjaChartsAdminRole() allows, which is not
                // the same as being allowed to see every private draft that shows a chart.
                if (!current_user_can('read_post', $post['id'])) {
                    continue;
                }
                $usage[$chartId]['total']++;
                if (count($usage[$chartId]['items']) < self::MAX_POSTS_PER_CHART) {
                    $usage[$chartId]['items'][] = self::describe($post);
                }
            }
        }

        return $usage;
    }

    /**
     * Map of chart id => posts showing it ([id, type, status]), newest first, for every user.
     * The expensive half, so it is what gets cached.
     *
     * @return array
     */
    protected static function candidates()
    {
        $cached = get_transient(self::CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        $map = [];
        self::eachCandidatePost(function ($row) use (&$map) {
            foreach (self::chartIdsIn($row->post_content) as $chartId) {
                $map[$chartId][] = [
                    'id'     => (int) $row->ID,
                    'type'   => $row->post_type,
                    'status' => $row->post_status,
                ];
            }
        });

        set_transient(self::CACHE_KEY, $map, self::CACHE_TTL);

        return $map;
    }

    /**
     * Chart ids of the shortcodes in a piece of content. Uses WordPress' own shortcode parser,
     * so id="90", id='90', id=90, extra attributes and spacing all match, escaped [[ninja_charts]]
     * does not, and id 9 never matches id="90".
     *
     * @param string $content
     * @return int[]
     */
    public static function chartIdsIn($content)
    {
        if (!is_string($content) || false === strpos($content, '[ninja_charts')) {
            return [];
        }

        if (!preg_match_all('/' . get_shortcode_regex(['ninja_charts']) . '/', $content, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $ids = [];
        foreach ($matches as $match) {
            // [[ninja_charts id="1"]] is how a shortcode is written out as text
            if ('[' === $match[1] && ']' === $match[6]) {
                continue;
            }
            $atts = array_change_key_case((array) shortcode_parse_atts($match[3]), CASE_LOWER);
            // The shortcode reads its id with intval(), so this does the same
            $id = isset($atts['id']) ? intval($atts['id']) : 0;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    /**
     * Calls $callback with every post whose content contains "[ninja_charts", newest first,
     * reading CHUNK_SIZE rows per query.
     *
     * @param callable $callback
     */
    protected static function eachCandidatePost(callable $callback)
    {
        global $wpdb;

        $types = self::postTypes();
        if (!$types) {
            return;
        }

        $like         = '%' . $wpdb->esc_like('[ninja_charts') . '%';
        $typeHolders  = implode(', ', array_fill(0, count($types), '%s'));
        $stateHolders = implode(', ', array_fill(0, count(self::STATUSES), '%s'));
        $beforeId     = PHP_INT_MAX;

        do {
            $args = array_merge([$like, $beforeId], $types, self::STATUSES, [self::CHUNK_SIZE]);

            // Keyset paging on the primary key: every chunk costs the same however deep it is.
            // Only placeholder lists are interpolated; every value goes through prepare().
            // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT ID, post_type, post_status, post_content FROM {$wpdb->posts}
                WHERE post_content LIKE %s AND ID < %d
                AND post_type IN ({$typeHolders}) AND post_status IN ({$stateHolders})
                ORDER BY ID DESC LIMIT %d",
                $args
            ));
            // phpcs:enable

            if (!$rows) {
                return;
            }

            foreach ($rows as $row) {
                $beforeId = (int) $row->ID;
                $callback($row);
            }
        } while (count($rows) === self::CHUNK_SIZE);
    }

    /**
     * Post types a person can see or edit: public ones and those with an admin screen.
     *
     * @return string[]
     */
    protected static function postTypes()
    {
        $types = array_merge(
            get_post_types(['public' => true]),
            get_post_types(['show_ui' => true])
        );
        unset($types['attachment'], $types['revision']);

        return array_values(array_unique(array_map('strval', $types)));
    }

    /**
     * Title and links are resolved for the current user on every request: get_edit_post_link()
     * is empty without the capability.
     *
     * @param array $post An entry of candidates()
     * @return array
     */
    protected static function describe(array $post)
    {
        $postId = $post['id'];
        $type   = get_post_type_object($post['type']);
        $wpPost = get_post($postId);
        // The raw title: get_the_title() adds "Private:", which the status already says
        $title  = $wpPost ? html_entity_decode(wp_strip_all_tags($wpPost->post_title), ENT_QUOTES, get_bloginfo('charset')) : '';

        return [
            'id'         => $postId,
            'title'      => '' === trim($title) ? __('(no title)', 'ninja-charts') : $title,
            'type_label' => $type ? $type->labels->singular_name : $post['type'],
            'status'     => $post['status'],
            'edit_url'   => (string) get_edit_post_link($postId, 'raw'),
            'view_url'   => 'publish' === $post['status']
                ? (string) get_permalink($postId)
                : (string) get_preview_post_link($postId),
        ];
    }

    /**
     * Any post saved, trashed, restored or deleted can add or drop a shortcode, so the map is
     * rebuilt on its next read.
     *
     * Saves of menus, orders, changesets and other types that are never looked up leave it alone.
     *
     * @param int           $postId
     * @param \WP_Post|null $post
     */
    public static function flushCache($postId = 0, $post = null)
    {
        if ($postId && (wp_is_post_revision($postId) || wp_is_post_autosave($postId))) {
            return;
        }

        // On deleted_post the row is gone and the type unknown, so the map is cleared anyway
        $type = $post instanceof \WP_Post ? $post->post_type : get_post_type($postId);
        if ($type && !in_array($type, self::postTypes(), true)) {
            return;
        }

        delete_transient(self::CACHE_KEY);
    }
}
