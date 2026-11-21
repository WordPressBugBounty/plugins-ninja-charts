<?php

namespace NinjaCharts\App\Http\Controllers;

use NinjaCharts\Framework\Support\Arr;
use NinjaCharts\App\Models\NinjaCharts;
use NinjaCharts\App\Modules\Provider;
use NinjaCharts\App\Modules\RenderEngineRegistry;

class ExportController extends Controller
{
    public function exportCsv($id)
    {
        $id = intval($id);

        try {
            $ninjaChart = NinjaCharts::findOrFail($id);
        } catch (\Exception $e) {
            return $this->sendError(['message' => __('Chart not found', 'ninja-charts')], 404);
        }

        $provider = Provider::get($ninjaChart->data_source);
        if (is_wp_error($provider)) {
            return $this->sendError(['message' => $provider->get_error_message()], 400);
        }

        $chart_data = $provider->renderChart($ninjaChart);
        if ($chart_data === null) {
            return $this->sendError(['message' => __('No chart data could be generated', 'ninja-charts')], 422);
        }

        $csv      = $this->buildCsv($ninjaChart->render_engine, $chart_data);
        $filename = sanitize_file_name($ninjaChart->chart_name . '.csv');

        return $this->sendSuccess([
            'csv'      => $csv,
            'filename' => $filename,
        ]);
    }

    private function buildCsv($render_engine, $chart_data)
    {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- in-memory stream for fputcsv(), not a filesystem operation; WP_Filesystem has no equivalent.
        $output = fopen('php://temp', 'r+');

        $engine = RenderEngineRegistry::get($render_engine);
        if ($engine) {
            $engine->writeCsv($output, $chart_data);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- closing the in-memory php://temp stream opened above.
        fclose($output);

        return $csv;
    }
}
