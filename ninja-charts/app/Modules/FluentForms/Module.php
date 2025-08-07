<?php

namespace NinjaCharts\App\Modules\FluentForms;

use DateTime;
use NinjaCharts\Framework\Support\Arr;

use NinjaCharts\App\Traits\ChartDesignHelper;
use NinjaCharts\App\Traits\ChartGenerator;
use NinjaCharts\App\Models\FluentForm;
use NinjaCharts\App\Models\NinjaCharts;
use NinjaCharts\App\Models\FluentFormSubmission;
use NinjaCharts\App\Modules\CalculativeFields;
use NinjaCharts\App\Modules\ChartJsCharts\ChartJsModule;
use NinjaCharts\App\Modules\GoogleCharts\GoogleChartModule;

class Module
{
    public $data;
    use ChartDesignHelper;
    use ChartGenerator;

    public function getTableList()
    {
        $fluent_forms = FluentForm::select('id', 'title')->get();

        $list = [];

        foreach ($fluent_forms as $table) {
            $list[] = [
                'id'   => $table->id,
                'name' => $table->title
            ];
        }
        return apply_filters('ninja_charts_ffm_table_lists', $list);
    }

    public function getKeysByTable($table_id = null)
    {
        $type_keys = FluentForm::whereId($table_id)->select('form_fields')->first();
        if ($type_keys === null) {
            wp_send_json_error([
                'error' => 'Data not found!'
            ], 422);
        }
        $keys_types = [];
        $values = json_decode($type_keys->form_fields);
        foreach ($values->fields as $value) {
            if (isset($value->fields)) {
                foreach ($value->fields as $key => $value) {
                    if ($this->inputType(isset($value->element) ? $value->element : null)) {
                        $keys_types[] = [
                            'key'       => $value->attributes->name,
                            'label'     => $value->settings->label,
                            'data_type' => isset($value->element) ? $this->dataTypeFormat($value->element) : null
                        ];
                    }
                }
            } else {
                if ($this->inputType(isset($value->element) ? $value->element : null)) {
                    $keys_types[] = [
                        'key'       => $value->attributes->name,
                        'label'     => $value->settings->label,
                        'data_type' => $this->multipleSelectCheck($value)
                    ];
                }
            }
        }
        return apply_filters('ninja_charts_ffm_keys_by_table', $keys_types);
    }

    /**
     * global fields selection
     */
    public function inputType($type = null)
    {
        if (isset($type) && $type !== null) {
            if (in_array($this->dataTypeFormat($type), Fields::allowed())) {
                return true;
            }
            return false;
        }
    }

    public function getAllDataByTable($table_id = null, $keys = null, $chart_type = null, $extra_data = [], $id = null)
    {
        if (gettype($keys) === 'string') {
            $keys = json_decode($keys, true);
        }
        $ninja_chart = $id ? NinjaCharts::findOrFail($id) : null;
        $tableRows = $this->getTableRows($extra_data, $table_id, $ninja_chart);

        $labels = $this->labelFormat($keys, $tableRows, $field = 'response');
        $data = [
            "labels" => $labels,
            "tableRows" => $tableRows,
            "chart_type" => $chart_type,
            "keys" => $keys,
            "ninja_chart" => $ninja_chart,
            "data_source" => 'fluent_form',
            "field" => 'response'
        ];
        if (isset($extra_data['only_all_row'])) {
            return ($this->getFormSubmissionRows($table_id));
        } else {
            if ($extra_data['render_engine'] === 'google_charts') {
                return (new GoogleChartModule())->chartDataFormat($data, $extra_data);
            } else if($extra_data['render_engine'] === 'chart_js') {
                return (new ChartJsModule())->chartDataFormat($data, $extra_data);
            }
        }
    }

    public function renderChart($data)
    {
        $extra_data['render_engine'] = $data->render_engine;
        $keys = json_decode($data->final_keys, true);
        $chart_data = $this->getAllDataByTable($data->table_id, $keys, $data->chart_type, $extra_data, $data->id);
        return $chart_data;
    }

    public function getTableRows($extra_data, $table_id, $ninja_chart)
    {
        $options = isset($ninja_chart->options) ? json_decode($ninja_chart->options, true) : '';
        $rows = Arr::get($extra_data, 'rows') ? Arr::get($extra_data, 'rows') : Arr::get($options, 'row');
        $number = Arr::get($rows, 'number');

        $hasRange = Arr::get($rows, 'pick_range');
        $hasRangeDate = Arr::get($rows, 'pick_date');

        if ($hasRange && $hasRange === 'false' && $hasRangeDate && $hasRangeDate === 'false') {
            if ($number === '0') {
                $tableRows = $this->getFormSubmissionRows($table_id);
            } else {
                $tableRows = FluentFormSubmission::whereFormId($table_id)->where('status', '!=', 'trashed')->select('response', 'id')->paginate($number);
            }
        } elseif ($hasRange && $hasRange === 'true' && $hasRangeDate && $hasRangeDate === 'false') {
            if (isset($rows['selected_row']) && count($rows['selected_row']) > 0) {
                $tableRows = $this->selectFormSubmissionRow($table_id, $rows);
            } else {
                $tableRows = $this->getFormSubmissionRows($table_id);
            }
        } elseif ($hasRange && $hasRange === 'false' && $hasRangeDate && $hasRangeDate === 'true') {
            if (isset($rows['date_range']) && $rows['date_range'] !== null) {
                $tableRows = $this->getAllRowByDateTime($table_id, $rows);
            } else {
                $tableRows = $this->getFormSubmissionRows($table_id);
            }
        } else {
            $tableRows = $this->getFormSubmissionRows($table_id);
        }
        $field = 'response';
        $data = $tableRows->map(function ($items) use ($field){
            if (isset($items->$field)) {
               return json_decode($items->$field, true);
            }
        });
        $tableRows = $data->all();
        return apply_filters('ninja_charts_ffm_all_table_rows', $tableRows);
    }

    public function getAllRowByDateTime($table_id, $rows)
    {
        $dates =  Arr::get($rows, 'date_range');
        $date_from = isset($dates[0]) ? $dates[0] : '';
        $date_to = isset($dates[1]) ? $dates[1] : '';

        $dt_from = new DateTime(substr($date_from, 4, 11));
        $dt_to = new DateTime(substr($date_to, 4, 11));
        $from = $dt_from->format('Y-m-d 00:00:00');
        $to = $dt_to->format('Y-m-d 23:59:59');
        $tableRows = FluentFormSubmission::whereBetween('created_at', [$from, $to])
            ->whereFormId($table_id)
            ->where('status', '!=', 'trashed')
            ->select('response', 'id')->get();

        return apply_filters('ninja_charts_ffm_filter_by_date_rows', $tableRows);
    }

    public function getFormSubmissionRows($table_id = null)
    {
        return FluentFormSubmission::whereFormId($table_id)
            ->where('status', '!=', 'trashed')
            ->select('response', 'id')
            ->get();
    }

    public function selectFormSubmissionRow($table_id, $rows)
    {
        $rows = isset($rows['selected_row']) ? $rows['selected_row'] : '';
        $ids = [];
        foreach ($rows as $key => $value) {
            $ids[] = $value['id'];
        }

        $tableRows =  FluentFormSubmission::whereFormId($table_id)
            ->whereIn('id', $ids)
            ->select('response', 'id')
            ->get();
        return $tableRows;
    }

    /**
     * calculative fields selection
     */
    public function calculativeFields($field = null)
    {
        if (in_array($this->dataTypeFormat($field), CalculativeFields::type())) {
            return true;
        }
        return false;
    }

    public function dataTypeFormat($type = null)
    {
        $string_pos = stripos($type, '_');
        if ($string_pos) {
            $pos = $string_pos + 1;
        } else {
            $pos = $string_pos;
        }
        $string = substr($type, $pos, strlen($type));
        return $string;
    }

    public function multipleSelectCheck($value = null)
    {
        if (isset($value->element)) {
            if (isset($value->attributes->multiple) && $value->attributes->multiple === true) {
                return $this->dataTypeFormat('multiple-select');
            } else {
                return $this->dataTypeFormat($value->element);
            }
        }
    }
}
