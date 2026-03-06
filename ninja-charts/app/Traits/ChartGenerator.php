<?php

namespace NinjaCharts\App\Traits;

use NinjaCharts\Framework\Support\Arr;
use NinjaCharts\App\Modules\CalculativeFields;

trait ChartGenerator
{
    public function chartJsOtherChart($data, $extra_data = [])
    {
        $chart_data  = Arr::get($extra_data, 'chart_data');
        $tableRows   = Arr::get($data, 'tableRows');
        $keys        = Arr::get($data, 'keys');
        $ninja_chart = Arr::get($data, 'ninja_chart');
        $chart_type  = Arr::get($data, 'chart_type');
        $labels      = Arr::get($data, 'labels');
        $field       = Arr::get($data, 'field');

        $data_sets = [];
        $i         = 0;
        $rows      = $tableRows;

        foreach ($keys as $key) {
            $k             = Arr::get($key, 'key');
            $default_label = Arr::get($key, 'label');
            $border_color  = $this->borderColor($ninja_chart, $chart_type, $i);
            $label         = $this->label($ninja_chart, $chart_type, $i);
            $isCalculative = in_array(Arr::get($key, 'data_type'), CalculativeFields::type());

            if ($k !== Arr::get($labels, 'label_key') || $isCalculative) {
                $data_sets[] =
                    [
                        "label"                => $label ? $label : $default_label,
                        "backgroundColor"      => $this->allBackGroundColorFormat(
                            $ninja_chart,
                            $chart_type,
                            $rows,
                            $chart_data,
                            $i
                        ),
                        "pointBackgroundColor" => 'white',
                        "borderWidth"          => 1,
                        "borderColor"          => $border_color ? $border_color : $this->randomColor(),
                        //Data to be represented on y-axis
                        "data"                 => $chart_data ? $chart_data : $this->dataFormat($tableRows, $k, $field)
                    ];
                $i++;
            }
        }

        return $data_sets;
    }

    public function chartJsLineOrArea($data, $extra_data = [])
    {
        $chart_data  = Arr::get($extra_data, 'chart_data');
        $tableRows   = Arr::get($data, 'tableRows');
        $keys        = Arr::get($data, 'keys');
        $ninja_chart = Arr::get($data, 'ninja_chart');
        $chart_type  = Arr::get($data, 'chart_type');
        $labels      = Arr::get($data, 'labels');
        $field       = Arr::get($data, 'field');

        $data_sets = [];
        $i         = 0;
        $rows      = $tableRows;

        foreach ($keys as $key) {
            $k             = Arr::get($key, 'key');
            $default_label = Arr::get($key, 'label');
            $border_color  = $this->borderColor($ninja_chart, $chart_type, $i);
            $label         = $this->label($ninja_chart, $chart_type, $i);

            $line_tension = $this->lineTension($ninja_chart, $chart_type, $i);
            $lineWidth    = $this->lineWidth($ninja_chart, $chart_type, $i);

            $pointRadius      = $this->pointRadius($ninja_chart, $chart_type, $i);
            $background_color = $this->backgroundColor($ninja_chart, $chart_type, $i);
            $isCalculative    = in_array(Arr::get($key, 'data_type'), CalculativeFields::type());

            if ($k !== Arr::get($labels, 'label_key') || $isCalculative) {
                $data_sets[] =
                    [
                        "fill"                 => $this->areaChartFill($ninja_chart, $chart_type),
                        "label"                => $label ? $label : $default_label,
                        "backgroundColor"      => $background_color ? $background_color : $this->chartBackgroundColor(
                            $chart_type,
                            $rows
                        ),
                        "pointBackgroundColor" => 'white',
                        "borderWidth"          => $lineWidth,
                        "pointBorderWidth"     => 1,
                        "pointBorderColor"     => 'black',
                        "pointHoverRadius"     => 4,
                        "borderColor"          => $border_color ? $border_color : $this->randomColor(),
                        "tension"              => $line_tension,
                        "pointRadius"          => $pointRadius,
                        //Data to be represented on y-axis
                        "data"                 => $chart_data ? $chart_data : $this->dataFormat($tableRows, $k, $field)
                    ];
                $i++;
            }
        }

        return $data_sets;
    }


    public function chartJsBubbleOrScatterChart($data)
    {
        $tableRows   = Arr::get($data, 'tableRows');
        $chart_type  = Arr::get($data, 'chart_type');
        $ninja_chart = Arr::get($data, 'ninja_chart');
        $keys        = Arr::get($data, 'keys');

        $bg_color = $this->dynamicBackgroundColor($ninja_chart, $chart_type);
        $bd_color = $this->dynamicBorderColor($ninja_chart, $chart_type, $keys);

        $rows      = $tableRows;
        $allKey    = [];
        $data_sets = [];
        $data      = [];

        foreach ($keys as $key) {
            if (Arr::get($key, 'data_type') == 'number') {
                $allKey[] = Arr::get($key, 'key');
            }
        }

        foreach ($rows as $value) {
            $x = Arr::get($allKey, 0, null);
            $y = Arr::get($allKey, 1, null);
            $r = Arr::get($allKey, 2, null);

            $data[] = [
                'x' => $x ? (float)$value[$x] : null,
                'y' => $y ? (float)$value[$y] : null,
                'r' => $r ? (float)$value[$r] : null
            ];
        }

        $data_sets[] =
            [
                "label"                => ucwords($chart_type),
                "backgroundColor"      => isset($bg_color['bg_color'][0]) ? $bg_color['bg_color'][0] : $this->chartBackgroundColor(
                    $chart_type,
                    $rows
                ),
                "pointBackgroundColor" => 'white',
                "borderWidth"          => 1,
                "pointBorderColor"     => 'black',
                "pointHoverRadius"     => 4,
                "borderColor"          => isset($bd_color[0]) ? $bd_color[0] : $this->randomColor(),
                // Data to be represented on y-axis
                "data"                 => $data
            ];

        return $data_sets;
    }

    public function dataFormat($tableRows, $k, $field)
    {
        return array_map(function ($items) use ($k, $field) {
            if (isset($items[$k]) && $items[$k] != null) {
                return is_numeric($items[$k]) ? (float)$items[$k] : $items[$k];
            }

            return null;
        }, $tableRows);
    }
}
