<?php

namespace NinjaCharts\App\Modules;

use NinjaCharts\App\Modules\ChartJsCharts\ChartJsModule;
use NinjaCharts\App\Modules\GoogleCharts\GoogleChartModule;

class Provider
{
    public static function get($source)
    {
        $allowed = ['ninja_table', 'fluent_form', 'manual'];

        if ( ! in_array($source, $allowed, true)) {
            return new \WP_Error(
                'invalid_data_source',
                sprintf(
                    /* translators: %s: data source name */
                    __("Couldn't find %s data provider.", 'ninja-charts'),
                    sanitize_text_field($source)
                )
            );
        }

        if ($source === 'ninja_table' && defined('NINJA_TABLES_VERSION')) {
            return new NinjaTables\Module();
        } else if ($source === 'fluent_form' && defined('FLUENTFORM_VERSION')) {
            return new FluentForms\Module();
        } else if ($source === 'manual') {
            return new ManualModule();
        }

        return new \WP_Error(
            'plugin_not_active',
            sprintf(
                /* translators: %s: data source name */
                __("The required plugin for %s is not active.", 'ninja-charts'),
                sanitize_text_field($source)
            )
        );
    }

    public static function renderEngine($render_engine)
    {
        if ($render_engine === 'chart_js') {
            return new ChartJsModule();
        } else if ($render_engine === 'google_chart'){
            return new GoogleChartModule();
        }
    }
}
