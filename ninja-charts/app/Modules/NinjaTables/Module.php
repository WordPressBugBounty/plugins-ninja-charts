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
use NinjaCharts\App\Modules\RenderEngineRegistry;
use NinjaCharts\App\Modules\DataSourceInterface;
use NinjaCharts\App\Helpers\Helper;
use NinjaCharts\App\Constants\ChartConstants;

class Module implements DataSourceInterface
{
    use ChartDesignHelper;
    use ChartGenerator;

    public function getTableList()
    {
        $ninja_tables = NinjaTable::wherePostType('ninja-table')->select('ID', 'post_title')->get();

        $dragAndDropTables   = NinjaTableMeta::whereMetaValue('drag_and_drop')->get();
        $dragAndDropTableIds = [];
        $countTables         = count($dragAndDropTables);
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
        $values     = Helper::maybeUnserialize($type_keys->meta_value);
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
        if (is_string($keys)) {
            $keys = json_decode($keys, true);
        }
        // A few rows, to show what each column holds, before any row is read for the chart
        $sample = Helper::sampleSize($extra_data);
        if ($sample) {
            return DataTablesRows::applies($table_id)
                ? DataTablesRows::sample($table_id, $sample)
                : NinjaTableItem::whereTableId($table_id)->select('value', 'id')->orderBy('id', 'ASC')->limit($sample)->get();
        }

        $ninja_chart              = $id ? NinjaCharts::findOrFail($id) : null;
        $extra_data['chart_type'] = $chart_type;

        // Picking rows by hand lists every row. A DataTables table answers that from its own
        // storage, before the chart's rows are read, so the table is read only once.
        if (isset($extra_data['only_all_row']) && DataTablesRows::applies($table_id)) {
            $options = $ninja_chart && isset($ninja_chart->options) ? json_decode($ninja_chart->options, true) : [];
            $rows    = Arr::get($extra_data, 'rows') ?: Arr::get($options, 'row');

            return DataTablesRows::pickable($table_id, $rows);
        }

        $tableRows                = $this->getTableRows($extra_data, $table_id, $ninja_chart);
        $labels                   = $this->labelFormat($keys, $tableRows, $chart_type, $extra_data);
        $ntbDataProvider          = ninja_table_get_data_provider($table_id);

        $data = [
            "labels"      => $labels,
            "tableRows"   => $tableRows,
            "chart_type"  => $chart_type,
            "keys"        => $keys,
            "ninja_chart" => $ninja_chart,
            "data_source" => ChartConstants::SOURCE_NINJA_TABLE,
            "field"       => 'value'
        ];
        if ($ntbDataProvider === 'google-csv') {
            return ($this->checkRenderEngine($data, $extra_data));
        } elseif (isset($extra_data['only_all_row'])) {
            return ($this->getAllRowFromNinjaTableItem($table_id, $extra_data, $ninja_chart));
        } else {
            return ($this->checkRenderEngine($data, $extra_data));
        }
    }

    public function checkRenderEngine($data, $extra_data)
    {
        $engine = RenderEngineRegistry::get(Arr::get($extra_data, 'render_engine'));

        return $engine ? $engine->chartDataFormat($data, $extra_data) : [];
    }

    public function renderChart($data)
    {
        $extra_data['render_engine'] = $data->render_engine;
        $extra_data['chart_type']    = $data->chart_type;
        $keys                        = json_decode($data->final_keys, true);
        $chart_data                  = $this->getAllDataByTable(
            $data->table_id,
            $keys,
            $data->chart_type,
            $extra_data,
            $data->id
        );

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
        $options      = isset($ninja_chart->options) ? json_decode($ninja_chart->options, true) : '';
        $rows         = Arr::get($extra_data, 'rows') ? Arr::get($extra_data, 'rows') : Arr::get($options, 'row');
        $hasRange     = Arr::get($rows, 'pick_range');
        $hasRangeDate = Arr::get($rows, 'pick_date');
        $remote_csv   = false;
        // A DataTables table keeps its rows in its own storage, not in the shared items table
        if (DataTablesRows::applies($table_id)) {
            return apply_filters(
                'ninja_charts_ntm_all_table_rows',
                DataTablesRows::values($table_id, $rows, $this->dateBounds($rows))
            );
        }

        $sort         = $this->sortBy($table_id);

        if (ninja_table_get_data_provider($table_id) === 'google-csv') {
            $googleRows = $this->fetchGoogleCsvRows($table_id, $rows, $hasRange, $hasRangeDate);
            if ($googleRows !== null) {
                return apply_filters('ninja_charts_ntm_all_table_rows', $googleRows);
            }
        }

        $tableRows = $this->fetchFilteredRows(
            $table_id, $rows, $hasRange, $hasRangeDate, $sort, $extra_data, $ninja_chart, $remote_csv
        );

        if (!$remote_csv) {
            $field     = 'value';
            $data      = $tableRows->map(function ($items) use ($field) {
                if (isset($items->$field)) {
                    return json_decode($items->$field, true);
                }
            });
            $tableRows = $data->all();
        }

        return apply_filters('ninja_charts_ntm_all_table_rows', $tableRows);
    }

    private function fetchGoogleCsvRows($table_id, $rows, $hasRange, $hasRangeDate)
    {
        if (!($hasRange && $hasRange === 'true' || $hasRangeDate && $hasRangeDate === 'true')) {
            return null;
        }

        $tableRows = ninjaTablesGetTablesDataByID(
            $table_id,
            $tableColumns    = [],
            $defaultSorting  = false,
            $disableCache    = false,
            0,
            $skip            = false,
            $ownOnly         = false
        );

        if (!isset($rows['selected_row'])) {
            return $tableRows;
        }

        $uniqueKey   = apply_filters('ninja_charts_ntm_google_csv_unique_key', '');
        $selectedIds = array_column($rows['selected_row'], 'id');

        if ($uniqueKey) {
            $tableRows = array_filter($tableRows, function ($item) use ($selectedIds, $uniqueKey) {
                return in_array($item[$uniqueKey], $selectedIds);
            });
            return array_values($tableRows);
        }

        return array_values(array_intersect_key($tableRows, array_flip($selectedIds)));
    }

    private function fetchFilteredRows($table_id, $rows, $hasRange, $hasRangeDate, $sort, $extra_data, $ninja_chart, &$remote_csv)
    {
        $number = Arr::get($rows, 'number');

        if ($hasRange && $hasRange === 'false' && $hasRangeDate && $hasRangeDate === 'false') {
            return $this->fetchNumberLimitedRows($table_id, $rows, $number, $sort, $extra_data, $ninja_chart, $remote_csv);
        }

        if ($hasRange && $hasRange === 'true' && $hasRangeDate && $hasRangeDate === 'false') {
            if (isset($rows['selected_row']) && count($rows['selected_row']) > 0) {
                return $this->selectSpecificRowFromNinjaTableItem($table_id, $rows);
            }
            return $this->getAllRowFromNinjaTableItem($table_id, $extra_data, $ninja_chart);
        }

        if ($hasRange && $hasRange === 'false' && $hasRangeDate && $hasRangeDate === 'true') {
            if (isset($rows['date_range']) && $rows['date_range'] !== null) {
                return $this->getAllRowByDateTime($table_id, $rows);
            }
            return $this->getAllRowFromNinjaTableItem($table_id, $extra_data, $ninja_chart);
        }

        return $this->getAllRowFromNinjaTableItem($table_id, $extra_data, $ninja_chart);
    }

    private function fetchNumberLimitedRows($table_id, $rows, $number, $sort, $extra_data, $ninja_chart, &$remote_csv)
    {
        if (!NinjaTableItem::whereTableId($table_id)->exists()) {
            $limit      = $number === '0' ? false : $number;
            $remote_csv = true;
            return ninjaTablesGetTablesDataByID(
                $table_id,
                $tableColumns    = [],
                $defaultSorting  = false,
                $disableCache    = false,
                $limit,
                $skip            = false,
                $ownOnly         = false
            );
        }

        if ($number === '0') {
            return $this->getAllRowFromNinjaTableItem($table_id, $extra_data, $ninja_chart);
        }

        $order = Helper::getOrderBy(Arr::get($rows, 'order', 'ASC'));
        return NinjaTableItem::whereTableId($table_id)
                             ->select('value', 'id')
                             ->orderBy($sort, $order)
                             ->paginate($number);
    }

    /**
     * The chart's date range as the first and last moment of its days, or null when it is unusable.
     * The dates arrive as JavaScript date strings ("Mon Jan 01 2024 …"), so the day is cut from them.
     */
    private function dateBounds($rows)
    {
        $dates         = Arr::get(is_array($rows) ? $rows : [], 'date_range');
        $date_from     = isset($dates[0]) ? $dates[0] : '';
        $date_to       = isset($dates[1]) ? $dates[1] : '';
        $date_from_str = $date_from ? substr($date_from, 4, 11) : '';
        $date_to_str   = $date_to   ? substr($date_to,   4, 11) : '';

        if (!$date_from_str || !$date_to_str || strtotime($date_from_str) === false || strtotime($date_to_str) === false) {
            return null;
        }

        $dt_from = new DateTime($date_from_str);
        $dt_to   = new DateTime($date_to_str);

        return [$dt_from->format('Y-m-d 00:00:00'), $dt_to->format('Y-m-d 23:59:59')];
    }

    public function getAllRowByDateTime($table_id, $rows = [])
    {
        $bounds = $this->dateBounds($rows);
        if (!$bounds) {
            return NinjaTableItem::whereTableId($table_id)->whereRaw('0 = 1')->select('value', 'id')->get();
        }

        list($from, $to) = $bounds;

        $tableRows = NinjaTableItem::whereBetween('created_at', [$from, $to])
                                   ->whereTableId($table_id)
                                   ->select('value', 'id')
                                   ->get();

        return $tableRows;
    }

    public function getAllRowFromNinjaTableItem($table_id, $extra_data = [], $ninja_chart = null)
    {
        $options = $ninja_chart && isset($ninja_chart->options) ? json_decode($ninja_chart->options, true) : [];
        $rows    = Arr::get($extra_data, 'rows') ?: Arr::get($options, 'row');
        $order   = Helper::getOrderBy(Arr::get($rows, 'order', 'ASC'));
        $sort    = $this->sortBy($table_id);

        return NinjaTableItem::whereTableId($table_id)
                             ->select('value', 'id')
                             ->orderBy($sort, $order)
                             ->get();
    }

    public function selectSpecificRowFromNinjaTableItem($table_id, $rows)
    {
        $order = Helper::getOrderBy(Arr::get($rows, 'order', 'ASC'));

        $rows = isset($rows['selected_row']) ? $rows['selected_row'] : '';
        $ids  = [];
        foreach ($rows as $key => $value) {
            $ids[] = $value['id'];
        }

        $sort      = $this->sortBy($table_id);
        $tableRows = NinjaTableItem::whereTableId($table_id)
                                   ->whereIn('id', $ids)
                                   ->select('value', 'id')
                                   ->orderBy($sort, $order)
                                   ->get();

        return apply_filters('ninja_charts_ntm_selected_table_rows', $tableRows);
    }

    public function sortBy($table_id)
    {
        static $cache = [];
        if (!array_key_exists($table_id, $cache)) {
            $first = NinjaTableItem::whereTableId($table_id)->select('position')->first();
            $cache[$table_id] = isset($first['position']) ? 'position' : 'created_at';
        }
        return $cache[$table_id];
    }
}
