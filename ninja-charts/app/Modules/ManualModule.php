<?php

namespace NinjaCharts\App\Modules;

use NinjaCharts\Framework\Support\Arr;

use NinjaCharts\App\Traits\ChartDesignHelper;
use NinjaCharts\App\Models\NinjaCharts;
use NinjaCharts\App\Modules\ChartJsCharts\ChartJsModule;
use NinjaCharts\App\Modules\GoogleCharts\GoogleChartModule;

class ManualModule
{
    protected $request;
    use ChartDesignHelper;

    public function getTableList()
    {
        return 0;
    }

    public function getKeysByTable($table_id = null)
    {
        return '';
    }

    public function getAllDataByTable($table_id = null, $keys = null, $chart_type = null, $extra_data = [], $id = null)
    {
        if (gettype($keys) === 'string') {
            $keys = json_decode($keys, true);
        }

        $ninja_chart = $id ? NinjaCharts::findOrFail($id) : null;
        $manual_inputs = isset($extra_data['manual_inputs']) ? $extra_data['manual_inputs'] : json_decode($ninja_chart->manual_inputs,
            true);
        $labels = $this->labelFormat($manual_inputs);
        $data = [
            "labels" => $labels,
            "manual_inputs" => $manual_inputs,
            "chart_type" => $chart_type,
            "keys" => $keys,
            "ninja_chart" => $ninja_chart,
            "data_source" => 'manual_inputs',
            "field" => ''
        ];
        if ($extra_data['render_engine'] === 'google_charts') {
            return (new GoogleChartModule())->chartDataFormat($data, $extra_data);
        } else {
            if ($extra_data['render_engine'] === 'chart_js') {
                return (new ChartJsModule())->chartDataFormat($data, $extra_data);
            }
        }
    }

    public function labelFormat($rows)
    {
        $labels = [];
        foreach ($rows as $key => $value) {
            $labels[] = isset($value['text_input']) ? $value['text_input'] : '';
        }

        return apply_filters('ninja_charts_manual_formatted_labels', $labels);
    }

    public function chartJsOtherChart($data, $extra_data = [])
    {
        extract($data);
        $chart_data = isset($extra_data['manual_inputs']) ? $extra_data['manual_inputs'] : $extra_data;
        $data_sets = [];
        $rows = $manual_inputs;
        $keys = array_keys(end($manual_inputs));
        $i = 0;

        foreach ($keys as $key) {
            $border_color = $this->borderColor($ninja_chart, $chart_type, $i);
            $label = $this->label($ninja_chart, $chart_type, $i);

            if ($key !== 'text_input' && $key !== 'text-input') {
                $BgColor = [];

                if ($chart_type === 'pie' || $chart_type === 'doughnut' || $chart_type === 'polarArea' || $chart_type === 'funnel'){
                    $BgColor = $this->allBackGroundColorFormat($ninja_chart, $chart_type, $rows, $chart_data);
                } else {
                    $BgColor = $border_color;
                }

                $data_sets[] =
                    [
                        "label" => $label ? $label : $key,
                        "backgroundColor" => $BgColor ? $BgColor : $this->randomColor(),
                        "pointBackgroundColor" => 'white',
                        "borderWidth" => 1,
                        //Data to be represented on y-axis
                        "data" => $this->dataFormat($rows, $key, $chart_type)
                    ];

                if ($chart_type === 'funnel') {
                    $data_sets[$i]['borderColor'] = $border_color ? $border_color : '';
                } else {
                    $data_sets[$i]['borderColor'] = $border_color ? $border_color : $this->randomColor();
                }

                $i++;
            }
        }
        return apply_filters('ninja_charts_manual_data_sets_except_bubble_scatter_', $data_sets);
    }

    public function chartJsLineOrArea($data, $extra_data = [])
    {
        extract($data);
        $data_sets = [];
        $rows = $manual_inputs;
        $keys = array_keys(end($manual_inputs));
        $i = 0;

        foreach ($keys as $key) {
            $border_color = $this->borderColor($ninja_chart, $chart_type, $i);
            $label = $this->label($ninja_chart, $chart_type, $i);
            $line_tension = $this->lineTension($ninja_chart, $chart_type, $i);
            $pointRadius = $this->pointRadius($ninja_chart, $chart_type, $i);
            $background_color = $this->backgroundColor($ninja_chart, $chart_type, $i);
            $lineWidth = $this->lineWidth($ninja_chart, $chart_type, $i);

            if ($key !== 'text_input' && $key !== 'text-input') {
                $data_sets[] =
                    [
                        "fill"                 => $this->areaChartFill($ninja_chart, $chart_type),
                        "label"                => $label ? $label : $key,
                        "backgroundColor"      => $background_color ? $background_color : $this->chartBackgroundColor($chart_type, $rows),
                        "pointBackgroundColor" => 'white',
                        "borderWidth"          => $lineWidth,
                        "pointBorderWidth"     => 1,
                        "pointBorderColor"     => 'black',
                        "pointHoverRadius"     => 4,
                        "borderColor"          => $border_color ? $border_color : $this->randomColor(),
                        "pointRadius"          => $pointRadius,
                        "tension"              => $line_tension,
                        //Data to be represented on y-axis
                        "data"                 => $this->dataFormat($rows, $key, $chart_type)
                    ];
                $i++;
            }
        }
        return apply_filters('ninja_charts_manual_data_sets_except_bubble_scatter_', $data_sets);
    }

    public function chartJsBubbleOrScatterChart($data)
    {
        extract($data);
        $data_sets = [];
        $i = 0;
        $border_color = $this->borderColor($ninja_chart, $chart_type, $i);
        $label = $this->label($ninja_chart, $chart_type, $i);
        $background_color = $this->backgroundColor($ninja_chart, $chart_type, $i);

        $data_sets[] =
            [
                "label" => $label ? $label : ucwords($chart_type),
                "backgroundColor" => $background_color ? $background_color : $this->chartBackgroundColor($chart_type,
                    $manual_inputs),
                "pointBackgroundColor" => 'white',
                "borderWidth" => 1,
                "pointBorderColor" => 'black',
                "pointHoverRadius" => 4,
                "borderColor" => $border_color ? $border_color : $this->randomColor(),
                //  Data to be represented on y-axis
                "data" => $this->dataFormat($manual_inputs, $k = '', $chart_type)
            ];

        return apply_filters('ninja_charts_manual_data_sets_bubble_scatter_', $data_sets);
    }

    public function dataFormat($rows, $k, $chart_type)
    {
        $data = [];
        if ($chart_type === 'bubble' || $chart_type === 'scatter') {
            foreach ($rows as $key => $value) {
                $data[] = [
                    'x' => (float)Arr::get($value, 'x'),
                    'y' => (float)Arr::get($value, 'y'),
                    'r' => (float)Arr::get($value, 'r', '')
                ];
            }
        } else {
            foreach ($rows as $key => $value) {
                if (isset($value[$k]) && $value[$k] != NULL) {
                    $data[] = is_numeric($value[$k]) ? (float)$value[$k] : $value[$k];
                } else {
                    $data[] = null;
                }
            }
        }
        return $data;
    }

    public function renderChart($data)
    {
        $extra_data['render_engine'] = $data->render_engine;
        $keys = json_decode($data->final_keys, true);
        $chart_data = $this->getAllDataByTable($data->table_id, $keys, $data->chart_type, $extra_data, $data->id);
        return $chart_data;
    }
}
