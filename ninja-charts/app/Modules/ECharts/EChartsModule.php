<?php

namespace NinjaCharts\App\Modules\ECharts;

defined('ABSPATH') || exit;

use NinjaCharts\App\Constants\ChartConstants;
use NinjaCharts\App\Modules\ChartJsCharts\ChartJsModule;
use NinjaCharts\App\Services\Vite;
use NinjaCharts\Framework\Support\Arr;

/**
 * Apache ECharts: the engine for every chart created from 3.6 on.
 *
 * The server side reuses the Chart.js data pipeline. It reads all three data sources,
 * calculated fields and the saved series colours, and returns {labels, datasets}.
 * The ECharts render script turns that into an ECharts option in the browser, so
 * this class only adds what differs: its ID, chart types, view and scripts.
 * CSV export and starting colours are the Chart.js ones, as the data shape is the same.
 */
class EChartsModule extends ChartJsModule
{
    /**
     * ECharts-only type => the type whose data it reads: the same rows, columns and pipeline.
     * resources/admin/data/echartsTypes.mjs keeps the same map, so keep the two in step.
     */
    const DATA_FAMILY = [
        'pictorialBar'  => 'bar',
        'polarBar'      => 'bar',
        'stepLine'      => 'line',
        'themeRiver'    => 'line',
        'calendar'      => 'bar',
        'gauge'         => 'pie',
        'effectScatter' => 'scatter',
        'heatmap'       => 'bar',
        'boxplot'       => 'bar',
        'histogram'     => 'bar',
        'parallel'      => 'bar',
        'candlestick'   => 'bar',
        'map'           => 'pie',
    ];

    /**
     * The type whose data a chart type reads: itself, for the types every engine has.
     *
     * @param mixed $type
     */
    public static function dataFamily($type): string
    {
        $type = is_string($type) ? $type : '';

        return Arr::get(self::DATA_FAMILY, $type, $type);
    }

    public static function id(): string
    {
        return ChartConstants::ENGINE_ECHARTS;
    }

    /**
     * The chart types Ninja Charts offered before ECharts, under the same names,
     * so a new chart can be anything an old one could be.
     */
    public static function chartTypes(): array
    {
        return array_merge(
            ['bar', 'horizontalBar', 'line', 'area', 'pie', 'doughnut', 'polarArea', 'radar', 'bubble', 'scatter', 'combo', 'funnel'],
            array_keys(self::DATA_FAMILY)
        );
    }

    /**
     * Builds an ECharts-only type's data as its data family's, which the ECharts render script
     * then draws as the real type.
     */
    public function chartDataFormat($data, $extra_data = [])
    {
        $type   = Arr::get($data, 'chart_type');
        $family = self::dataFamily($type);

        if ($family !== $type) {
            $data['chart_type'] = $family;
            if (is_array($extra_data)) {
                $extra_data['chart_type'] = $family;
            }
            // The colour helpers reuse saved colours only for the stored chart's own type
            $chart = Arr::get($data, 'ninja_chart');
            if (is_object($chart)) {
                $chart             = clone $chart;
                $chart->chart_type = $family;
                $data['ninja_chart'] = $chart;
            }
        }

        return parent::chartDataFormat($data, $extra_data);
    }

    public function seedColors(string $chart_type, array $manual_inputs): array
    {
        return parent::seedColors(self::dataFamily($chart_type), $manual_inputs);
    }

    public function shortcodeView(): string
    {
        return 'public.echarts';
    }

    public function enqueueShortcodeAssets(): void
    {
        // One bundle: ECharts (only the chart types and components used) plus the render script.
        // Not under public/js, which the build copies verbatim and would overwrite the bundle.
        Vite::enqueueScript(
            'ninja-charts-echarts-render',
            'public/echarts/echarts-render.js',
            ['ninja_charts_loader', 'ninja-charts-device', 'ninja-charts-hover']
        );

        wp_localize_script('ninja-charts-echarts-render', 'ninjaChartsEChartsPublic', [
            'ajax_url'  => admin_url('admin-ajax.php'),
            // Loaded only when a PDF export is asked for
            'jspdf_url' => Vite::assetsUrl('public/js/library/jspdf.umd.min.js'),
            // Map files for map charts (resources/maps, copied to assets/maps by the build)
            'maps_url'  => Vite::assetsUrl('maps/'),
            'maps_ver'  => NINJA_CHARTS_VERSION,
        ]);
    }
}
