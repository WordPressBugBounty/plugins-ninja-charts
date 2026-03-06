<?php

if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * @var $router \NinjaCharts\Framework\Http\Router
 */
$router->namespace('')->group(function ($router) {
    require __DIR__ . '/api.php';
});
