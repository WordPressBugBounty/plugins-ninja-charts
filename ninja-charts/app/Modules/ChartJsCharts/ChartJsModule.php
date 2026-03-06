<?php

namespace NinjaCharts\App\Modules\ChartJsCharts;

use NinjaCharts\Framework\Support\Arr;

use NinjaCharts\App\Traits\ChartGenerator;
use NinjaCharts\App\Traits\ChartDesignHelper;
use NinjaCharts\App\Modules\NinjaTables\CalculativeModule as NinjaTableCalculative;
use NinjaCharts\App\Modules\FluentForms\CalculativeModule as FluentFormCalculative;
use NinjaCharts\App\Modules\FluentForms\Module;
use NinjaCharts\App\Modules\ManualModule;

class ChartJsModule
{

    use ChartGenerator;
    use ChartDesignHelper;

    public function chartDataFormat($data, $extra_data = [])
    {
        $data_source = Arr::get($data, 'data_source');

        if ($data_source === 'ninja_table') {
            return $this->ninjaTableDataFormat($data, $extra_data);
        } else {
            if ($data_source === 'fluent_form') {
                return $this->fluentFormDataFormat($data, $extra_data);
            } else {
                if ($data_source === 'manual_inputs') {
                    return $this->manualDataFormat($data, $extra_data);
                }
            }
        }
    }

    public function ninjaTableDataFormat($data, $extra_data)
    {
        $c_labels = [];
        $dataType = Arr::get($data, 'keys.0.data_type');
        if ($dataType === 'selection') {
            $chart_datas = (new NinjaTableCalculative())->chartData($data);
            $chart_data  = $chart_datas['chart_data'];
            $c_labels    = $chart_datas['labels'];
            $data_type   = $dataType;
        } else {
            $chart_data = $extra_data;
            $data_type  = '';
        }

        $chart_data = $this->commonChartRender($data, $extra_data, $c_labels, $chart_data, $data_type, $data_type);

        return apply_filters('ninja_charts_ntm_all_data_by_table', $chart_data);
    }

    public function fluentFormDataFormat($data, $extra_data)
    {
        $dataType = Arr::get($data, 'keys.0.data_type');
        $c_labels = [];
        if ((new Module)->calculativeFields($dataType)) {
            $chart_datas = (new FluentFormCalculative())->chartData($data);
            $chart_data  = $chart_datas['chart_data'];
            $c_labels    = $chart_datas['labels'];
            $data_type   = $dataType;
        } else {
            $chart_data = $extra_data;
            $data_type  = '';
        }

        $chart_data = $this->commonChartRender($data, $extra_data, $c_labels, $chart_data, $data_type);

        return apply_filters('ninja_charts_ffm_data_by_table', $chart_data);
    }

    public function manualDataFormat($data, $extra_data)
    {
        $chart_type    = Arr::get($data, 'chart_type');
        $manual_inputs = Arr::get($data, 'manual_inputs');
        $ninja_chart   = Arr::get($data, 'ninja_chart');
        $labels        = Arr::get($data, 'labels');

        $data_sets = '';
        if ($manual_inputs === '') {
            return '';
        }
        if ($chart_type === 'bubble' || $chart_type === 'scatter') {
            $data_sets = (new ManualModule)->chartJsBubbleOrScatterChart($data);
        } elseif ($chart_type === 'line' || $chart_type === 'area' || $chart_type === 'combo') {
            $data_sets = (new ManualModule)->chartJsLineOrArea($data, $extra_data);
        } else {
            $data_sets = (new ManualModule)->chartJsOtherChart($data, $extra_data);
        }
        if ($chart_type === 'combo') {
            $data_sets[count($data_sets) - 1]['type'] = 'line';
        }
        $labels     = $this->piePolarDoughnutLabelFormat($ninja_chart, $chart_type, $labels, null, $extra_data);
        $chart_data = [
            "labels"   => $labels,
            "datasets" => $data_sets,
        ];

        return apply_filters('ninja_charts_manual_all_data_by_table', $chart_data);
    }

    public function commonChartRender($data, $extra_data, $c_labels, $chart_data, $data_type)
    {
        $chart_type = Arr::get($data, 'chart_type');
        $ninja_chart = Arr::get($data, 'ninja_chart');

        if ($chart_type === 'bubble' || $chart_type === 'scatter') {
            $data_sets = $this->chartJsBubbleOrScatterChart($data);
        } elseif ($chart_type === 'line' || $chart_type === 'area' || $chart_type === 'combo') {
            $data_sets = $this->chartJsLineOrArea($data, $chart_data);
        } else {
            $data_sets = $this->chartJsOtherChart($data, $chart_data);
        }
        if ($chart_type === 'combo') {
            $data_sets[count($data_sets) - 1]['type'] = 'line';
        }
        $new_labels = Arr::get($data, 'labels.labels');

        $labels     = $this->piePolarDoughnutLabelFormat(
            $ninja_chart,
            $chart_type,
            $new_labels,
            $c_labels,
            $extra_data
        );
        $chart_data = [
            "labels"   => $labels,
            "datasets" => $data_sets,
        ];

        if ($data_type) {
            $chart_data['data_type'] = $data_type;
        }

        return $chart_data;
    }
}
