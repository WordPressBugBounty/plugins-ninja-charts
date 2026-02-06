<?php

namespace NinjaCharts\App\Hooks\Handlers;

use NinjaCharts\App\App;
use NinjaCharts\App\Hooks\Handlers\ActivationHandler;
use NinjaCharts\Framework\Support\Arr;

class AdminMenuHandler
{
    public function add()
    {
        $capability = ninjaChartsAdminRole();

        add_menu_page(
            __('Ninja Charts', 'ninja-charts'),
            __('Ninja Charts', 'ninja-charts'),
            $capability,
            'ninja-charts',
            [$this, 'render'],
            $this->getMenuIcon(),
            6
        );
        add_submenu_page(
            'ninja-charts',
            __('Charts', 'ninja-charts'),
            __('Charts', 'ninja-charts'),
            $capability,
            'ninja-charts',
            [$this, 'render']
        );
        add_submenu_page(
            'ninja-charts',
            __('Add Chart', 'ninja-charts'),
            __('Add Chart', 'ninja-charts'),
            $capability,
            'ninja-charts#/add-chart',
            [$this, 'render']
        );
        add_submenu_page(
            'ninja-charts',
            __('Get help', 'ninja-charts'),
            __('Get help', 'ninja-charts'),
            $capability,
            'ninja-charts#/support',
            [$this, 'render']
        );

        if (defined('NINJA_TABLES_VERSION')) {
            remove_menu_page('ninja-charts');
        }
    }

    public function render($url = null)
    {
        $config = App::getInstance('config');

        $name = $config->get('app.name');

        $slug = $config->get('app.slug');

        $baseUrl = apply_filters('ninja_charts/fluent_connector_base_url', admin_url('admin.php?page=' . $slug . '#/'));

        $menuItems = [
            [
                'key'       => 'chartList',
                'label'     => __('Charts', 'ninja-charts'),
                'permalink' => $baseUrl . 'chart-list',
            ],
            [
                'key'       => 'support',
                'label'     => __('Support', 'ninja-charts'),
                'permalink' => $baseUrl . 'support'
            ]
        ];

        $app    = App::getInstance();
        $assets = $app['url.assets'];

        App::make('view')->render('admin.menu', [
            'name'      => $name,
            'slug'      => $slug,
            'menuItems' => $menuItems,
            'baseUrl'   => $baseUrl,
            'logo'      => $assets . 'images/icon_small.png',
        ]);
    }

    public function checkForDbMigration()
    {
        if (!get_site_option('_ninja_charts_installed_version')) {
            $app = App::getInstance();
            (new ActivationHandler($app))->handle();
        }
    }

    public function enqueueAssets()
    {
        $page = sanitize_text_field(Arr::get($_GET, 'page', '')); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['page']) && $page === 'ninja-charts') { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $this->enqueueCssJs();
        }
    }

    public function enqueueCssJs()
    {
        $app = App::getInstance();

        $assets = $app['url.assets'];

        $slug = $app->config->get('app.slug');

        wp_enqueue_style(
            $slug . '_admin_app',
            $assets . '/admin/css/admin.css',
            [],
            NINJA_CHARTS_VERSION
        );

        do_action($slug . '_loading_app'); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound

        wp_enqueue_script(
            $slug . '_admin_app_start',
            $assets . '/admin/js/start.js',
            array(),
            '1.0',
            true
        );

        wp_enqueue_script(
            $slug . '_global_admin',
            $assets . 'admin/js/global_admin.js',
            array('jquery'),
            '1.0',
            true
        );

//        wp_enqueue_script(
//            $slug.'_admin_app_chart_label_format',
//            $assets . 'common/js/chartjs-plugin-datalabels.js',
//            array($slug.'_global_admin'),
//            '1.0',
//            true
//        );
        // Google Charts
        wp_enqueue_script(
            $slug . '_admin_app_google_charts',
            $assets . 'common/js/google-charts.js',
            array($slug . '_global_admin'),
            '1.0',
            true
        );

        $page = sanitize_text_field(Arr::get($_GET, 'page', '')); //phpcs:ignore WordPress.Security.NonceVerification.Recommended

        if ($page && defined('NINJA_TABLES_VERSION') && $page === 'ninja-charts') {
            wp_enqueue_script(
                'ninja_charts_extend_menu',
                $assets . '/admin/js/menu-active.js',
                array($slug . '_global_admin'),
                '1.0',
                true
            );
        }

        $currentUser = get_user_by('ID', get_current_user_id());

        wp_localize_script($slug . '_admin_app_start', 'fluentFrameworkAdmin', [
            'slug'       => $slug = $app->config->get('app.slug'),
            'nonce'      => wp_create_nonce($slug),
            'rest'       => $this->getRestInfo($app),
            'brand_logo' => $this->getMenuIcon(),
            'asset_url'  => $assets,
            'me'         => [
                'id'        => $currentUser->ID,
                'full_name' => trim($currentUser->first_name . ' ' . $currentUser->last_name),
                'email'     => $currentUser->user_email
            ],
        ]);
    }

    protected function getRestInfo($app)
    {
        $ns  = $app->config->get('app.rest_namespace');
        $ver = $app->config->get('app.rest_version');

        return [
            'base_url'  => esc_url_raw(rest_url()),
            'url'       => rest_url($ns . '/' . $ver),
            'nonce'     => wp_create_nonce('wp_rest'),
            'namespace' => $ns,
            'version'   => $ver
        ];
    }

    protected function getMenuIcon()
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 155.9 164.98"><defs><style>.cls-1{fill:#fff;}</style></defs><title>dashboard_icon</title><g id="Layer_2" data-name="Layer 2"><g id="Layer_1-2" data-name="Layer 1"><path class="cls-1" d="M153.46,160.11H138.84V70a2.43,2.43,0,0,0-2.43-2.43H112.05A2.43,2.43,0,0,0,109.61,70v90.13h-17V96.78a2.43,2.43,0,0,0-2.43-2.44H65.77a2.44,2.44,0,0,0-2.44,2.44v63.33h-17V109a2.43,2.43,0,0,0-2.43-2.44H19.49A2.44,2.44,0,0,0,17.05,109v51.15H2.44a2.44,2.44,0,1,0,0,4.87h151a2.44,2.44,0,1,0,0-4.87Z"/><path class="cls-1" d="M9.74,85.26h.15c.85,0,21.17-.74,48.07-10.33a188.9,188.9,0,0,0,43.95-22.38,178,178,0,0,0,36.93-34.09v5.91a4.88,4.88,0,0,0,9.75,0V4.87A4.87,4.87,0,0,0,143.11,0L123.62,2.47a4.87,4.87,0,0,0,1.21,9.67l7.33-.91C107.38,42.07,76.9,57.79,55,65.63,29.51,74.78,9.79,75.51,9.59,75.51a4.88,4.88,0,0,0,.15,9.75Z"/></g></g></svg>';

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}

