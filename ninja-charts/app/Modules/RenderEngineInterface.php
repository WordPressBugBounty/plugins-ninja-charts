<?php

namespace NinjaCharts\App\Modules;

defined('ABSPATH') || exit;

/**
 * A rendering engine for saved charts: the shortcode pipeline, the admin preview
 * (processData) and CSV export. The Gutenberg block has its own contract in
 * app/Blocks/Engines/ChartEngineInterface.php.
 *
 * Register an engine in RenderEngineRegistry; nothing else needs to know its ID.
 */
interface RenderEngineInterface
{
    /**
     * Engine identifier, stored in the render_engine column.
     */
    public static function id(): string;

    /**
     * Chart types this engine draws (the chart_type values it accepts).
     *
     * @return string[]
     */
    public static function chartTypes(): array;

    /**
     * Turn rows from a data source into the chart data this engine's renderer reads.
     *
     * @param array $data       labels, rows, keys, chart_type, ninja_chart and data_source
     * @param array $extra_data request extras (render_engine, chart_type, manual_inputs, ...)
     * @return mixed
     */
    public function chartDataFormat($data, $extra_data = []);

    /**
     * Enqueue the scripts the shortcode needs to draw this engine's charts.
     */
    public function enqueueShortcodeAssets(): void;

    /**
     * View the shortcode renders, e.g. 'public.chart_js'.
     */
    public function shortcodeView(): string;

    /**
     * Write chart data from chartDataFormat() to an open CSV stream.
     *
     * @param resource $output
     * @param mixed    $chart_data
     */
    public function writeCsv($output, $chart_data): void;

    /**
     * Random starting colours for a chart created without datasets (CSV import).
     *
     * @return array{datasets: array, series: array}
     */
    public function seedColors(string $chart_type, array $manual_inputs): array;
}
