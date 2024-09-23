<?php

namespace NinjaCharts\App\Http\Controllers;

use NinjaCharts\App\Traits\ChartDesignHelper;
use NinjaCharts\App\Models\NinjaCharts;
use NinjaCharts\App\Traits\ChartOption;
use NinjaCharts\App\Modules\Provider;

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
        $data = ninjaChartsSanitizeArray($this->request->data);
        $ninjaCharts = NinjaCharts::store($data);

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
        NinjaCharts::duplicate($id);

        return $this->sendSuccess([
            'success' => 200
        ]);
    }

    public function processData()
    {
        $table_id = intval($this->request->table_id);
        $keys = (gettype($this->request->keys) === 'array') ? ninjaChartsSanitizeArray($this->request->keys) : sanitize_text_field($this->request->keys);
        $chart_type = sanitize_text_field($this->request->chart_type);
        $extra_data = ninjaChartsSanitizeArray($this->request->get('extra_data', []));
        $id = intval($this->request->get('id', ''));
        $data_source = sanitize_text_field($this->request->data_source);

        if (isset($extra_data['manual_inputs']) || $keys) {
            $chart_data =  Provider::get($data_source)->getAllDataByTable($table_id, $keys, $chart_type, $extra_data, $id);
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
        $ninjaCharts = NinjaCharts::remove($ids);

        return $this->send([
            'deleted' => 204
        ]);
    }
}
