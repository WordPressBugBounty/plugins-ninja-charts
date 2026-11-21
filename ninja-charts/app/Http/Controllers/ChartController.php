<?php

namespace NinjaCharts\App\Http\Controllers;

use NinjaCharts\Framework\Support\Arr;
use NinjaCharts\App\Traits\ChartDesignHelper;
use NinjaCharts\App\Models\NinjaCharts;
use NinjaCharts\App\Traits\ChartOption;
use NinjaCharts\App\Modules\Provider;
use NinjaCharts\App\Modules\RenderEngineRegistry;
use NinjaCharts\App\Helpers\DeviceOptions;
use NinjaCharts\App\Helpers\HoverOptions;
use NinjaCharts\App\Services\ChartUsage;

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

    /**
     * Where each chart's shortcode is used: GET charts/usage?ids=90,86,85.
     */
    public function usage()
    {
        $ids = ChartUsage::parseIds($this->request->get('ids', ''));

        return $this->sendSuccess([
            'usage' => (object) ChartUsage::forCharts($ids),
        ]);
    }

    public function store()
    {
        // A missing/non-array `data` envelope must fail validation (422), not TypeError the validator (500)
        $requestData = is_array($this->request->data) ? $this->request->data : [];

        $this->validate($requestData, [
            'render_engine' => 'required',
            'chart_type' => 'required',
            'data_source' => 'required',
        ]);

        $data = $this->sanitizeChartData($requestData);
        // A body-injected id cannot steer this create onto an existing row: NinjaCharts::store()
        // picks the update target from its second argument only (never passed here) and ignores
        // $data['id']. Pinned by chart_create_ignores_a_body_injected_id_and_never_overwrites_another_chart.

        // When no datasets are provided (e.g. CSV import), generate random colors server-side.
        if (empty($data['datasets']) && !empty($data['manualInputData'])) {
            $data = $this->injectRandomColors($data);
        }

        $ninjaCharts = NinjaCharts::store($data);

        return $this->sendSuccess([
            'ninja_charts' => $ninjaCharts
        ]);
    }

    /**
     * Sanitizes every value, then keeps only the supported per-device and hover settings.
     */
    private function sanitizeChartData(array $requestData): array
    {
        $data = ninjaChartsSanitizeArray($requestData);

        if (isset($data['options']) && is_array($data['options'])) {
            $data['options'] = DeviceOptions::sanitizeOptions($data['options']);
            $data['options'] = HoverOptions::sanitizeOptions($data['options']);
        }

        return $data;
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

        $engine = RenderEngineRegistry::get($render_engine);
        if (!$engine) {
            return $data;
        }

        $seeded        = $engine->seedColors((string) $chart_type, is_array($manual_inputs) ? array_values($manual_inputs) : []);
        $datasets      = $seeded['datasets'];
        $series_colors = $seeded['series'];

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
        // Same guard as store(): absent envelope → 422, not a validator TypeError
        $requestData = is_array($this->request->data) ? $this->request->data : [];

        $this->validate($requestData, [
            'render_engine' => 'required',
            'chart_type' => 'required',
            'data_source' => 'required',
        ]);

        $data = $this->sanitizeChartData($requestData);
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

        $extra_data = ninjaChartsSanitizeArray($this->request->get('extra_data', []));
        $id = intval($this->request->get('id', ''));
        $data_source = sanitize_text_field($this->request->data_source);

        $render_engine = Arr::get($extra_data, 'render_engine', '');
        $engine        = RenderEngineRegistry::get($render_engine);
        if (!$engine) {
            return $this->sendError(['message' => __('Invalid render engine', 'ninja-charts')], 400);
        }

        // Each engine draws only its own types: echarts or chart_js with ColumnChart has no renderer
        if (!in_array($chart_type, $engine::chartTypes(), true)) {
            return $this->sendError(['message' => __('Invalid chart type', 'ninja-charts')], 400);
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
