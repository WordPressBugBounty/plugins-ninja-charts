<?php

namespace NinjaCharts\App\Http\Controllers;

use NinjaCharts\Framework\Support\Arr;
use NinjaCharts\App\Traits\ChartDesignHelper;
use NinjaCharts\App\Models\NinjaCharts;
use NinjaCharts\App\Traits\ChartOption;
use NinjaCharts\App\Modules\Provider;
use NinjaCharts\App\Constants\ChartConstants;

class ChartController extends Controller
{
    use ChartDesignHelper, ChartOption;

    public function index()
    {
        $perPage = intval($this->request->get('per_page', 10));
        $keyword = sanitize_text_field($this->request->keyword);
        $ninjaCharts = NinjaCharts::getChartData($keyword, $perPage);

        return $this->sendSuccess([
            'ninja_charts' => $ninjaCharts
        ]);
    }

    public function store()
    {
        $this->validate($this->request->data, [
            'render_engine' => 'required',
            'chart_type' => 'required',
            'data_source' => 'required',
        ]);

        $data = ninjaChartsSanitizeArray($this->request->data);
        unset($data['id']); // Body-injected id must never trigger an update on the create endpoint

        // When no datasets are provided (e.g. CSV import), generate random colors server-side.
        if (empty($data['datasets']) && !empty($data['manualInputData'])) {
            $data = $this->injectRandomColors($data);
        }

        $ninjaCharts = NinjaCharts::store($data);

        return $this->sendSuccess([
            'ninja_charts' => $ninjaCharts
        ]);
    }

    private function injectRandomColors(array $data): array
    {
        $render_engine  = Arr::get($data, 'render_engine');
        $chart_type     = Arr::get($data, 'chart_type');
        $manual_inputs  = Arr::get($data, 'manualInputData', []);
        $options        = Arr::get($data, 'options', []);
        if (!is_array($options)) {
            $options = [];
        }

        $pie_chartjs    = ['pie', 'doughnut', 'polarArea', 'funnel'];
        $pie_google     = ['PieChart', 'DonutChart'];
        $xy_chartjs     = ['bubble', 'scatter'];

        $datasets      = [];
        $series_colors = [];

        if ($render_engine === ChartConstants::ENGINE_CHART_JS) {
            if (in_array($chart_type, $pie_chartjs, true)) {
                $colors = array_map(function () { return $this->randomColor(); }, $manual_inputs);
                $datasets[]    = ['backgroundColor' => $colors, 'borderColor' => $colors];
                $series_colors = array_map(function ($c, $row) {
                    return ['color' => $c, 'label' => isset($row['text_input']) ? (string) $row['text_input'] : ''];
                }, $colors, array_values($manual_inputs));
            } elseif (in_array($chart_type, $xy_chartjs, true)) {
                $color         = $this->randomColor();
                $datasets[]    = ['backgroundColor' => $color, 'borderColor' => $color];
                $series_colors = [['color' => $color]];
            } else {
                // Line-type: one colour per series column (every key except text_input)
                $first_row  = !empty($manual_inputs) ? (array) $manual_inputs[0] : [];
                $num_series = max(1, count(array_filter(array_keys($first_row), function ($k) { return $k !== 'text_input'; })));
                for ($i = 0; $i < $num_series; $i++) {
                    $color         = $this->randomColor();
                    $datasets[]    = ['backgroundColor' => $color, 'borderColor' => $color];
                    $series_colors[] = ['color' => $color];
                }
            }
        } elseif ($render_engine === ChartConstants::ENGINE_GOOGLE_CHARTS) {
            if (in_array($chart_type, $pie_google, true)) {
                $series_colors = array_map(function ($row) {
                    return ['color' => $this->randomColor(), 'label' => isset($row['text_input']) ? (string) $row['text_input'] : ''];
                }, array_values($manual_inputs));
            } else {
                $first_row  = !empty($manual_inputs) ? (array) $manual_inputs[0] : [];
                $num_series = max(1, count(array_filter(array_keys($first_row), function ($k) { return $k !== 'text_input'; })));
                for ($i = 0; $i < $num_series; $i++) {
                    $series_colors[] = ['color' => $this->randomColor()];
                }
            }
        }

        if (!empty($datasets)) {
            $data['datasets'] = json_encode($datasets);
        }
        if (!empty($series_colors)) {
            $options['series'] = $series_colors;
            $data['options']   = $options;
        }

        return $data;
    }

    public function update($id = null)
    {
        $id = intval($id);
        if ($id < 1) {
            return $this->sendError(['message' => __('Invalid chart ID', 'ninja-charts')], 400);
        }
        $this->validate($this->request->data, [
            'render_engine' => 'required',
            'chart_type' => 'required',
            'data_source' => 'required',
        ]);

        $data = ninjaChartsSanitizeArray($this->request->data);
        $ninjaCharts = NinjaCharts::store($data, $id); // $id comes from the URL, not the body

        return $this->sendSuccess([
            'ninja_charts' => $ninjaCharts
        ]);
    }

    public function find($id = null)
    {
        $id = intval($id);
        $ninjaCharts = NinjaCharts::findOrFail($id);
        $ninjaCharts = $this->undefinedChartOptionsAppend($ninjaCharts);

        return $this->sendSuccess([
            'ninja_charts' => $ninjaCharts
        ]);
    }

    public function duplicate($id)
    {
        $id = intval($id);
        $ninjaCharts = NinjaCharts::duplicate($id);

        return $this->sendSuccess([
            'ninja_charts' => $ninjaCharts
        ]);
    }

    public function processData()
    {
        $table_id = intval($this->request->table_id);
        $keys = (is_array($this->request->keys)) ? ninjaChartsSanitizeArray($this->request->keys) : sanitize_text_field($this->request->keys);
        $chart_type = sanitize_text_field($this->request->chart_type);

        $allowed_chart_types = [
            // Chart.js types
            'bar', 'horizontalBar', 'line', 'area', 'pie', 'doughnut',
            'polarArea', 'radar', 'bubble', 'scatter', 'combo', 'funnel',
            // Google Charts types
            'ColumnChart', 'BarChart', 'LineChart', 'AreaChart', 'PieChart',
            'DonutChart', 'ScatterChart', 'BubbleChart', 'ComboChart', 'Histogram',
        ];
        if (!in_array($chart_type, $allowed_chart_types, true)) {
            return $this->sendError(['message' => __('Invalid chart type', 'ninja-charts')], 400);
        }

        $extra_data = ninjaChartsSanitizeArray($this->request->get('extra_data', []));
        $id = intval($this->request->get('id', ''));
        $data_source = sanitize_text_field($this->request->data_source);

        $render_engine = Arr::get($extra_data, 'render_engine', '');
        if (!in_array($render_engine, [ChartConstants::ENGINE_CHART_JS, ChartConstants::ENGINE_GOOGLE_CHARTS], true)) {
            return $this->sendError(['message' => __('Invalid render engine', 'ninja-charts')], 400);
        }

        if (isset($extra_data['manual_inputs']) || $keys) {
            $provider = Provider::get($data_source);
            if (is_wp_error($provider)) {
                return $this->sendError([
                    'message' => $provider->get_error_message()
                ], 400);
            }
            $chart_data = $provider->getAllDataByTable($table_id, $keys, $chart_type, $extra_data, $id);
            if ($chart_data === null) {
                return $this->sendError(['message' => __('No chart data could be generated for the given configuration', 'ninja-charts')], 422);
            }
        } else {
            $chart_data = '';
        }

        return $this->sendSuccess([
            'chart_data' => $chart_data
        ]);
    }

    public function destroy()
    {
        $ids = ninjaChartsSanitizeArray($this->request->ids);
        NinjaCharts::remove($ids);

        return $this->send([
            'deleted' => 204
        ]);
    }
}
