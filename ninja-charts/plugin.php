<?php
defined('ABSPATH') or die;

/**
 * Plugin Name: Ninja Charts - Interactive Charts and Graphs
 * Description: Ninja Charts - Best WP Charts Plugin for WordPress
 * Version: 3.4.0
 * Author: WPManageNinja LLC
 * Author URI: https://wpmanageninja.com/
 * Plugin URI: https://wpmanageninja.com/ninja-charts
 * License: GPL-2.0+
 * Text Domain: ninja-charts
 * Domain Path: /language
 */

$ninja_charts_info = get_file_data(__FILE__, array('Version' => 'Version'), false);
defined('NINJA_CHARTS_VERSION') or define('NINJA_CHARTS_VERSION', $ninja_charts_info['Version']);
define('NINJA_CHARTS_FILE', __FILE__);
define('NINJA_CHARTS_DIR', plugin_dir_path(__FILE__));
define('NINJA_CHARTS_URL', plugin_dir_url(__FILE__));


require __DIR__ . '/vendor/autoload.php';

call_user_func(function ($bootstrap) {
    $bootstrap(__FILE__);
}, require(__DIR__ . '/boot/app.php'));
