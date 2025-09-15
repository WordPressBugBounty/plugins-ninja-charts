<?php

namespace NinjaCharts\App\Modules\NinjaTables;

use DateTime;
use NinjaCharts\Framework\Support\Arr;

use NinjaCharts\App\Traits\ChartDesignHelper;
use NinjaCharts\App\Traits\ChartGenerator;
use NinjaCharts\App\Models\NinjaTable;
use NinjaCharts\App\Models\NinjaCharts;
use NinjaCharts\App\Models\NinjaTableItem;
use NinjaCharts\App\Models\NinjaTableMeta;
use NinjaCharts\App\Modules\ChartJsCharts\ChartJsModule;
use NinjaCharts\App\Modules\GoogleCharts\GoogleChartModule;

class Module
{
    use ChartDesignHelper;
    use ChartGenerator;
    public function getTableList()
    {
        $ninja_tables = NinjaTable::wherePostType('ninja-table')->select('ID', 'post_title')->get();

        $dragAndDropTables = NinjaTableMeta::whereMetaValue('drag_and_drop')->get();
        $dragAndDropTableIds    = [];
        $countTables = count($dragAndDropTables);
        for ($i = 0; $i < $countTables; $i++) {
            $dragAndDropTableIds[$i] = $dragAndDropTables[$i]->post_id;
        }

        $list = [];
        foreach ($ninja_tables as $table) {
            if (!in_array($table->ID, $dragAndDropTableIds)) {
                $list[] = [
                    'id'   => $table->ID,
                    'name' => $table->post_title,
                ];
            }
        }
        return apply_filters('ninja_charts_ntm_table_lists', $list);
    }

    public function getKeysByTable($table_id = null)
    {
        $type_keys = NinjaTableMeta::wherePostId($table_id)->where('meta_key', '_ninja_table_columns')->first();
        if ($type_keys === null) {
            wp_send_json_error([
                'error' => 'Data not found!'
            ], 422);
        }
        $values = unserialize($type_keys->meta_value);
        $keys_types = [];
        foreach ($values as $value) {
            if ($this->inputType(Arr::get($value, 'data_type'))) {
                $keys_types[] = [
                    'key'       => Arr::get($value, 'key'),
                    'label'     => Arr::get($value, 'name'),
                    'data_type' => Arr::get($value, 'data_type')
                ];
            }
        }
        return apply_filters('ninja_charts_ntm_keys_by_table', $keys_types);
    }

    public function getAllDataByTable($table_id = null, $keys = null, $chart_type = null, $extra_data = [], $id = null)
    {
        if (gettype($keys) === 'string') {
            $keys = json_decode($keys, true);
        }
        $ninja_chart = $id ? NinjaCharts::findOrFail($id) : null;
        $tableRows = $this->getTableRows($extra_data, $table_id, $ninja_chart);
        $labels = $this->labelFormat($keys, $tableRows, $chart_type, $extra_data);
        $ntbDataProvider = ninja_table_get_data_provider($table_id);

        $data = [
            "labels" =>$labels,
            "tableRows" =>$tableRows,
            "chart_type"=>$chart_type,
            "keys"=>$keys,
            "ninja_chart"=>$ninja_chart,
            "data_source" => 'ninja_table',
            "field" => 'value'
        ];
        if ($ntbDataProvider === 'google-csv') {
            return ($this->checkRenderEngine($data, $extra_data));
        } else if (isset($extra_data['only_all_row'])) {
            return ($this->getAllRowFromNinjaTableItem($table_id));
        } else {
            return ($this->checkRenderEngine($data, $extra_data));
        }
    }

    public function checkRenderEngine($data, $extra_data)
    {
        if ($extra_data['render_engine'] === 'google_charts') {
            return (new GoogleChartModule())->chartDataFormat($data, $extra_data);
        } else if($extra_data['render_engine'] === 'chart_js') {
            return (new ChartJsModule())->chartDataFormat($data, $extra_data);
        }
    }

    public function renderChart($data)
    {
        $extra_data['render_engine'] = $data->render_engine;
        $keys = json_decode($data->final_keys, true);
        $chart_data = $this->getAllDataByTable($data->table_id, $keys, $data->chart_type, $extra_data, $data->id);
        return $chart_data;
    }

    public function inputType($data_type)
    {
        if (in_array($data_type, Fields::allowed())) {
            return true;
        }
        return false;
    }

    public function getTableRows($extra_data, $table_id, $ninja_chart)
    {
        $options = isset($ninja_chart->options) ? json_decode($ninja_chart->options, true) : '';
        $rows = Arr::get($extra_data, 'rows') ? Arr::get($extra_data, 'rows') : Arr::get($options, 'row');
        $number = Arr::get($rows, 'number');

        $hasRange = Arr::get($rows, 'pick_range');
        $hasRangeDate = Arr::get($rows, 'pick_date');
        $remote_csv = false;
        $order = Arr::get($rows, 'order', 'ASC');
        $sort = $this->sortBy($table_id);

        $ntbDataProvider = ninja_table_get_data_provider($table_id);

        if ($ntbDataProvider  === 'google-csv') {
            if ($hasRange && $hasRange === 'true' || $hasRangeDate && $hasRangeDate === 'true') {
                $tableRows = ninjaTablesGetTablesDataByID($table_id, $tableColumns = [], $defaultSorting = false, $disableCache = false, 0, $skip = false, $ownOnly = false);

                if (isset($rows['selected_row'])) {
                    $uniqueKey = apply_filters('ninja_charts_ntm_google_csv_unique_key', '');
                    $selectedIds = array_column($rows['selected_row'], 'id');
                    if ($uniqueKey) {
                        $tableRows = array_filter($tableRows, function ($item) use ($selectedIds, $uniqueKey) {
                            return in_array($item[$uniqueKey], $selectedIds);
                        });
                        $tableRows = array_values($tableRows);
                    } else {
                        $selectedIds = array_flip($selectedIds);
                        $tableRows = array_values(array_intersect_key($tableRows, $selectedIds));
                    }
                }

                return apply_filters('ninja_charts_ntm_all_table_rows', $tableRows);
            }
        }

        if ($hasRange && $hasRange === 'false' && $hasRangeDate && $hasRangeDate === 'false') {
            $count = $this->getAllRowFromNinjaTableItem($table_id);
            if ($count->count() === 0) {
                //  when no data found from DB table
                $limit = $number === '0' ? false : $number;
                $remote_csv = true;
                $tableRows = ninjaTablesGetTablesDataByID($table_id, $tableColumns = [], $defaultSorting = false, $disableCache = false, $limit, $skip = false, $ownOnly = false);
            } else {
                if ($number === '0') {
                    $tableRows = $this->getAllRowFromNinjaTableItem($table_id);
                } else {
                    $tableRows = NinjaTableItem::whereTableId($table_id)
                    ->select('value', 'id')
                    ->orderBy($sort, sanitize_sql_orderby($order))
                    ->paginate($number);
                }
            }
        } elseif ($hasRange && $hasRange === 'true' && $hasRangeDate && $hasRangeDate === 'false') {
            if (isset($rows['selected_row']) && count($rows['selected_row']) > 0) {
                $tableRows = $this->selectSpecificRowFromNinjaTableItem($table_id, $rows);
            } else {
                $tableRows = $this->getAllRowFromNinjaTableItem($table_id);
            }
        } elseif ($hasRange && $hasRange === 'false' && $hasRangeDate && $hasRangeDate === 'true') {
            if (isset($rows['date_range']) && $rows['date_range'] !== null) {
                $tableRows = $this->getAllRowByDateTime($table_id, $rows);
            } else {
                $tableRows = $this->getAllRowFromNinjaTableItem($table_id);
            }
        } else {
            $tableRows = $this->getAllRowFromNinjaTableItem($table_id);
        }

        if (!$remote_csv) {
            $field = 'value';
            $data = $tableRows->map(function ($items) use ($field) {
                if (isset($items->$field)) {
                    return json_decode($items->$field, true);
                }
            });
            $tableRows = $data->all();
        }

        return apply_filters('ninja_charts_ntm_all_table_rows', $tableRows);
    }

    public function getAllRowByDateTime($table_id, $rows = [])
    {
        $dates =  Arr::get($rows, 'date_range');
        $date_from = isset($dates[0]) ? $dates[0] : '';
        $date_to = isset($dates[1]) ? $dates[1] : '';

        $dt_from = new DateTime(substr($date_from, 4, 11));
        $dt_to = new DateTime(substr($date_to, 4, 11));
        $from = $dt_from->format('Y-m-d 00:00:00');
        $to = $dt_to->format('Y-m-d 23:59:59');

        $tableRows = NinjaTableItem::whereBetween('created_at', [$from, $to])
            ->whereTableId($table_id)
            ->select('value', 'id')
            ->get();
        return $tableRows;
    }

    public function getAllRowFromNinjaTableItem($table_id)
    {
        $request = ninjaChartsSanitizeArray($_REQUEST); //phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $rows = Arr::get($request, 'extra_data.rows') ? Arr::get($request, 'extra_data.rows') : Arr::get($request, 'options.row');
        $order = Arr::get($rows, 'order', 'ASC');
        $sort = $this->sortBy($table_id);
        return NinjaTableItem::whereTableId($table_id)
        ->select('value', 'id')
        ->orderBy($sort, sanitize_sql_orderby($order))
        ->get();
    }

    public function selectSpecificRowFromNinjaTableItem($table_id, $rows)
    {
        $order = Arr::get($rows, 'order', 'ASC');
        $rows = isset($rows['selected_row']) ? $rows['selected_row'] : '';
        $ids = [];
        foreach ($rows as $key => $value) {
            $ids[] = $value['id'];
        }

        $sort = $this->sortBy($table_id);
        $tableRows =  NinjaTableItem::whereTableId($table_id)
        ->whereIn('id', $ids)
        ->select('value', 'id')
        ->orderBy($sort, sanitize_sql_orderby($order))
        ->get();

        return apply_filters('ninja_charts_ntm_selected_table_rows', $tableRows);
    }

    public function getAllRow($table_id = null)
    {
        return $this->getAllRowFromNinjaTableItem($table_id);
    }

    public function sortBy($table_id)
    {
        $first = NinjaTableItem::whereTableId($table_id)->select('position')->first();
        return isset($first['position']) ? 'position' : 'created_at';
    }
}
