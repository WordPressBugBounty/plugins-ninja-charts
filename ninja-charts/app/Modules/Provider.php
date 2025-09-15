<?php

namespace NinjaCharts\App\Modules;

use NinjaCharts\App\Modules\ChartJsCharts\ChartJsModule;
use NinjaCharts\App\Modules\GoogleCharts\GoogleChartModule;

class Provider
{
    public static function get($source)
    {
        if ($source === 'ninja_table' && (defined('NINJA_TABLES_VERSION'))) {
            return new NinjaTables\Module();
        } else if ($source === 'fluent_form' && (defined('FLUENTFORM_VERSION'))) {
            return new FluentForms\Module();
        } else if ($source === 'manual') {
            return new ManualModule();
        }

        echo '<h2 style="text-align: center; margin-top: 20px;">'
             . esc_html__( "Couldn't find ", 'ninja-charts' )
             . esc_html( $source )
             . esc_html__( " data provider.", 'ninja-charts' )
             . '</h2>';
        exit();
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
