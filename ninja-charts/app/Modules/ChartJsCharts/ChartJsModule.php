<?php

namespace NinjaCharts\App\Modules\ChartJsCharts;

use NinjaCharts\Framework\Support\Arr;

use NinjaCharts\App\Traits\ChartGenerator;
use NinjaCharts\App\Traits\ChartDesignHelper;
use NinjaCharts\App\Modules\NinjaTables\CalculativeModule as NinjaTableCalculative;
use NinjaCharts\App\Modules\FluentForms\CalculativeModule as FluentFormCalculative;
use NinjaCharts\App\Modules\FluentForms\Module;
use NinjaCharts\App\Modules\ManualModule;
use NinjaCharts\App\Constants\ChartConstants;
use NinjaCharts\App\App;
use NinjaCharts\App\Modules\RenderEngineInterface;

class ChartJsModule implements RenderEngineInterface
{

    use ChartGenerator;
    use ChartDesignHelper;

    public static function id(): string
    {
        return ChartConstants::ENGINE_CHART_JS;
    }

    public static function chartTypes(): array
    {
        return [
            'bar', 'horizontalBar', 'line', 'area', 'pie', 'doughnut',
            'polarArea', 'radar', 'bubble', 'scatter', 'combo', 'funnel',
        ];
    }

    public function shortcodeView(): string
    {
        return 'public.chart_js';
    }

    public function enqueueShortcodeAssets(): void
    {
        $app = App::getInstance();
        $assets = $app['url.assets'];

        wp_enqueue_script(
            'chartjs',
            $assets . 'public/js/library/chart.umd.js',
            array('jquery'),
            '4.4.2',
            true
        );

        wp_enqueue_script(
            'chartjs_plugin_labels',
            $assets . 'common/js/chartjs-plugin-datalabels.js',
            array('chartjs'),
            '2.0.0',
            true
        );

        wp_enqueue_script(
            'chartjs-chart-funnel',
            $assets . 'public/js/library/chartjs-chart-funnel.umd.min.js',
            array('chartjs'),
            '4.2.0',
            true
        );

        // Registered only — loaded on demand by render.js when a PDF export is triggered.
        wp_register_script(
            'ninja_charts_jspdf',
            $assets . 'public/js/library/jspdf.umd.min.js',
            array(),
            '2.5.1',
            true
        );

        wp_enqueue_script(
            'chart_js_chart_render_js',
            $assets . 'public/js/render.js',
            array('chartjs', 'ninja_charts_loader', 'ninja-charts-device', 'ninja-charts-hover'),
            NINJA_CHARTS_VERSION,
            true
        );

        wp_localize_script('chart_js_chart_render_js', 'chartJSPublic', [
            'ajax_url'  => admin_url('admin-ajax.php'),
            'jspdf_url' => $assets . 'public/js/library/jspdf.umd.min.js',
        ]);
    }

    public function writeCsv($output, $chart_data): void
    {
        $labels   = Arr::get($chart_data, 'labels', []);
        $datasets = Arr::get($chart_data, 'datasets', []);

        $header = ['Label'];
        foreach ($datasets as $dataset) {
            $header[] = Arr::get($dataset, 'label', '');
        }
        fputcsv($output, $header);

        foreach ($labels as $i => $label) {
            $row = [$label];
            foreach ($datasets as $dataset) {
                $row[] = isset($dataset['data'][$i]) ? $dataset['data'][$i] : '';
            }
            fputcsv($output, $row);
        }
    }

    public function seedColors(string $chart_type, array $manual_inputs): array
    {
        $datasets = [];
        $series   = [];

        if (in_array($chart_type, ['pie', 'doughnut', 'polarArea', 'funnel'], true)) {
            $colors = array_map(function () { return $this->randomColor(); }, $manual_inputs);
            $datasets[] = ['backgroundColor' => $colors, 'borderColor' => $colors];
            $series     = array_map(function ($c, $row) {
                return ['color' => $c, 'label' => isset($row['text_input']) ? (string) $row['text_input'] : ''];
            }, $colors, array_values($manual_inputs));
        } elseif (in_array($chart_type, ['bubble', 'scatter'], true)) {
            $color      = $this->randomColor();
            $datasets[] = ['backgroundColor' => $color, 'borderColor' => $color];
            $series     = [['color' => $color]];
        } else {
            // Line-type: one colour per series column (every key except text_input)
            $first_row  = !empty($manual_inputs) ? (array) $manual_inputs[0] : [];
            $num_series = max(1, count(array_filter(array_keys($first_row), function ($k) { return $k !== 'text_input'; })));
            for ($i = 0; $i < $num_series; $i++) {
                $color      = $this->randomColor();
                $datasets[] = ['backgroundColor' => $color, 'borderColor' => $color];
                $series[]   = ['color' => $color];
            }
        }

        return ['datasets' => $datasets, 'series' => $series];
    }

    public function chartDataFormat($data, $extra_data = [])
    {
        $data_source = Arr::get($data, 'data_source');

        if ($data_source === ChartConstants::SOURCE_NINJA_TABLE) {
            return $this->ninjaTableDataFormat($data, $extra_data);
        } else {
            if ($data_source === ChartConstants::SOURCE_FLUENT_FORM) {
                return $this->fluentFormDataFormat($data, $extra_data);
            } else {
                if ($data_source === ChartConstants::SOURCE_MANUAL_DATA) {
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
