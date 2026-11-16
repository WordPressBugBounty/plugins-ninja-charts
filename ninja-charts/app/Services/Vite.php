<?php

namespace NinjaCharts\App\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enqueues bundled assets from either the Vite dev server or the built assets/ folder.
 *
 * `npm run dev` writes the dev server URL to the .vite-hot file in the plugin root; it never
 * ships, because build.sh and .distignore leave it out. Callers pass the assets/-relative
 * path, which in dev maps to its source entry and is served as an ES module.
 *
 * Files copied verbatim to assets/ (public/js, common/js) keep their plain enqueues.
 */
class Vite
{
    const HOT_FILE = '.vite-hot';

    /**
     * Built asset path (relative to assets/) => source path (relative to resources/).
     * Keep in step with the entries in vite.config.mjs.
     *
     * The TinyMCE button is left out: TinyMCE loads it by URL, so it always uses the built file.
     */
    protected static $entries = [
        'admin/js/start.js'                        => 'admin/start.js',
        'admin/js/global_admin.js'                 => 'admin/global_admin.js',
        'admin/js/ninja-charts-gutenblock-build.js' => 'admin/tinymce/gutenBlock.js',
        'block/js/ninja-charts-block.js'           => 'block/js/ninja-charts-block.jsx',
        'admin/css/admin.css'                      => 'scss/admin.scss',
        'admin/css/gutenblock.css'                 => 'scss/gutenblock.scss',
        'admin/css/preview.css'                    => 'scss/preview.scss',
        'block/css/panel.css'                      => 'block/styles/panel.scss',
    ];

    protected static $devUrl = null;
    protected static $moduleHandles = [];
    protected static $filterAdded = false;

    public static function isDev()
    {
        return static::devUrl() !== '';
    }

    /**
     * The dev server URL with a trailing slash, or an empty string when it is not running.
     *
     * A crashed server can leave the hot file behind, so the server is also probed: if it is
     * not answering, the built assets load instead.
     */
    protected static function devUrl()
    {
        if (static::$devUrl !== null) {
            return static::$devUrl;
        }

        static::$devUrl = '';

        $hotFile = NINJA_CHARTS_DIR . static::HOT_FILE;

        if (!is_readable($hotFile)) {
            return static::$devUrl;
        }

        $url  = trailingslashit(trim((string) file_get_contents($hotFile))); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        $host = wp_parse_url($url, PHP_URL_HOST);

        if (wp_parse_url($url, PHP_URL_SCHEME) !== 'http' || !in_array($host, ['localhost', '127.0.0.1'], true)) {
            return static::$devUrl;
        }

        // Probe this plugin's entry: any other project's Vite server on the port has @vite/client too.
        $response = wp_remote_head($url . 'resources/admin/start.js', ['timeout' => 1]);

        if (wp_remote_retrieve_response_code($response) === 200) {
            static::$devUrl = $url;
        }

        return static::$devUrl;
    }

    public static function assetsUrl($path = '')
    {
        return NINJA_CHARTS_URL . 'assets/' . ltrim($path, '/');
    }

    /**
     * The cache-busting version for a built asset.
     *
     * The plugin version alone does not change when a build does, so a rebuilt
     * bundle must include the file's own mtime to get a new URL.
     */
    protected static function assetVersion($asset)
    {
        $file = NINJA_CHARTS_DIR . 'assets/' . ltrim($asset, '/');

        if (!file_exists($file)) {
            return NINJA_CHARTS_VERSION;
        }

        return NINJA_CHARTS_VERSION . '.' . filemtime($file);
    }

    public static function enqueueScript($handle, $asset, $deps = [], $inFooter = true)
    {
        static::registerScript($handle, $asset, $deps, $inFooter);

        wp_enqueue_script($handle);
    }

    /** For handles that something else enqueues, such as a block's editorScript. */
    public static function registerScript($handle, $asset, $deps = [], $inFooter = true)
    {
        if (static::isDev() && ($source = static::devSource($asset))) {
            static::registerDevModule($handle, $source, $deps, $inFooter);
            return;
        }

        wp_register_script($handle, static::assetsUrl($asset), $deps, static::assetVersion($asset), $inFooter);
    }

    public static function enqueueStyle($handle, $asset, $deps = [], $media = 'all')
    {
        if (static::isDev() && ($source = static::devSource($asset))) {
            // Vite serves .scss as a JS module that injects the styles, so it goes in as a script.
            // Suffix the handle, as a style and a script may share a name.
            static::registerDevModule($handle . '-vite-css', $source, [], false);
            wp_enqueue_script($handle . '-vite-css');
            return;
        }

        wp_enqueue_style($handle, static::assetsUrl($asset), $deps, static::assetVersion($asset), $media);
    }

    /** For handles that something else enqueues, such as a block's editorStyle. */
    public static function registerStyle($handle, $asset, $deps = [], $media = 'all')
    {
        if (static::isDev() && ($source = static::devSource($asset))) {
            // A block's editor canvas is an iframe, which a JS module in the main document cannot
            // reach. With ?direct Vite serves the compiled CSS, so the handle is a real stylesheet
            // that WordPress copies into the iframe too. Style changes show on reload, not by HMR.
            wp_register_style($handle, static::devUrl() . $source . '?direct', $deps, null, $media); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- dev server only

            return;
        }

        wp_register_style($handle, static::assetsUrl($asset), $deps, static::assetVersion($asset), $media);
    }

    protected static function devSource($asset)
    {
        $asset = ltrim($asset, '/');

        return isset(static::$entries[$asset]) ? 'resources/' . static::$entries[$asset] : null;
    }

    protected static function registerDevModule($handle, $source, $deps, $inFooter)
    {
        if (!in_array('ninja-charts-vite-client', static::$moduleHandles, true)) {
            wp_register_script('ninja-charts-vite-client', static::devUrl() . '@vite/client', [], null, false); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- dev server only, and ?ver= would load a second copy of the module
            static::markAsModule('ninja-charts-vite-client');
        }

        $deps[] = 'ninja-charts-vite-client';

        wp_register_script($handle, static::devUrl() . $source, $deps, null, $inFooter); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- dev server only, and ?ver= would load a second copy of the module
        static::markAsModule($handle);
    }

    protected static function markAsModule($handle)
    {
        static::$moduleHandles[] = $handle;

        if (!static::$filterAdded) {
            static::$filterAdded = true;
            add_filter('script_loader_tag', [static::class, 'moduleTag'], 10, 2);
        }
    }

    /**
     * Adds type="module" through script_loader_tag rather than the WP 6.5+ script modules API,
     * to keep older WP supported and wp_localize_script working. Modules are deferred, so
     * localized data still prints first.
     */
    public static function moduleTag($tag, $handle)
    {
        if (!in_array($handle, static::$moduleHandles, true)) {
            return $tag;
        }

        // $tag can also hold the handle's inline scripts, so only change the one with a src.
        return preg_replace_callback('/<script\b[^>]*\ssrc=[^>]*>/i', function ($matches) {
            $script = preg_replace('/\stype=(["\'])[^"\']*\1/i', '', $matches[0]);

            return preg_replace('/^<script\b/i', '<script type="module"', $script);
        }, $tag);
    }
}
