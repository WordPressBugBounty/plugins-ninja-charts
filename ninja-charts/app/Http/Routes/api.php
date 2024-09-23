<?php

/**
 * @var $router NinjaCharts\App\Http\Router
 */


$router->withPolicy('UserPolicy')->group(function ($app) {
    $app->get('charts', 'ChartController@index');
    $app->post('charts', 'ChartController@store');
    $app->get('charts/{id}', 'ChartController@find')->int('id');
    $app->post('charts/{id}/duplicate', 'ChartController@duplicate')->int('id');
    $app->post('process', 'ChartController@processData');
    $app->post('remove', 'ChartController@destroy');
});

/*
 * sources Route
 */
$router->withPolicy('SourcePolicy')->prefix('sources')->group(function ($app) {
    $app->get('/', 'SourceController@index');
    $app->get('/{sourceId}', 'SourceController@find')->int('sourceId');
    $app->get('csv-data/{tableId}', 'SourceController@processGoogleCSVData')->int('tableId');
});

$router->get('ninjatable-data-provider/{tableId}', 'SourceController@sourceName')->int('tableId');
