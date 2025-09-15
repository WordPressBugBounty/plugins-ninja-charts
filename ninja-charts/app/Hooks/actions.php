<?php

/**
 * All registered action's handlers should be in app\Hooks\Handlers,
 * addAction is similar to add_action and addCustomAction is just a
 * wrapper over add_action which will add a prefix to the hook name
 * using the plugin slug to make it unique in all wordpress plugins,
 * ex: $app->addCustomAction('foo', ['FooHandler', 'handleFoo']) is
 * equivalent to add_action('slug-foo', ['FooHandler', 'handleFoo']).
 */

/**
 * @var $app WPFluent\Foundation\Application
 */

$app->addAction('admin_menu', 'AdminMenuHandler@add');
$app->addAction('admin_enqueue_scripts', 'AdminMenuHandler@enqueueAssets');
$app->addAction('init', 'TinyMce@addChartsToEditor');
$app->addAction('init', 'TinyMce@gutenBlockLoad');

// disabled update-nag
add_action('admin_init', function () {
    $disablePages = [
        'ninja-charts'
    ];

    if (isset($_GET['page']) && in_array(sanitize_text_field($_GET['page']), $disablePages)) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        remove_all_actions('admin_notices');
    }
});

$app->addAction('wp_loaded', function() {
    (new \NinjaCharts\App\Http\Controllers\ShortCodeController())->ninjaChartsShortCode();
});

$app->addAction('wp_loaded',  'PreviewHandler@preview');
