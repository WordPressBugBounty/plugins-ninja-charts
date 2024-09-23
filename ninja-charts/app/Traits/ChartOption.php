<?php

namespace NinjaCharts\App\Traits;

use NinjaCharts\Framework\Support\Arr;

trait ChartOption
{
    public function undefinedChartOptionsAppend($ninja_charts)
    {
        $options = json_decode($ninja_charts->options, true);
        if(!isset($ninja_charts['row'])) {
            $ninja_charts['row'] = $options['row'];
        }
        if (!isset($options['layout'])) {
            $options['layout'] = [
                'padding'=>[
                    'left'=>0,
                    'right'=>0,
                    'top'=>0,
                    'bottom'=>0
                ]
            ];
        }
        if (!isset($options['animation'])) {
            $options['animation'] = "linear";
        }

        $ninja_charts['options'] = json_encode($options);
        return $ninja_charts;
    }

}
