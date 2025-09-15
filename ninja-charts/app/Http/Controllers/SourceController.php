<?php

namespace NinjaCharts\App\Http\Controllers;

use NinjaCharts\App\Traits\ChartDesignHelper;
use NinjaCharts\App\Traits\ChartOption;
use NinjaCharts\App\Modules\Provider;

class SourceController extends Controller
{
    use ChartDesignHelper, ChartOption;

    public function index()
    {
        $data_source = sanitize_text_field($this->request->data_source);

        if ($this->pluginActivationCheck($data_source)) {
            $provider = Provider::get($data_source);
            $tableList = $provider->getTableList();

            if (is_wp_error($provider)) {
                return $this->sendError($provider);
            } else {
                return $this->sendSuccess([
                    'table_list' => $tableList
                ]);
            }
        } else {
            $plugins = [
                'ninja_table' => 'Ninja Tables',
                'fluent_form' => 'Fluent Forms'
            ];

            $plugin = $plugins[$data_source];

            return $this->sendError([
                'message'=> "$plugin is not available!"
            ], 400);
        }
    }

    public function pluginActivationCheck($data_source)
    {
        if ($data_source === 'ninja_table') {
            if (function_exists('ninja_tables_boot')) {
                return true;
            } else {
                return false;
            }
        } else if ($data_source === 'fluent_form') {
            if (function_exists('wpFluentForm')) {
                return true;
            } else {
                return false;
            }
        }
    }

    public function find($sourceId)
    {
        $sourceId = intval($sourceId);
        $data_source = sanitize_text_field($this->request->data_source);
        $type_keys = Provider::get($data_source)->getKeysByTable($sourceId);

        return $this->sendSuccess([
            'type_keys' => $type_keys
        ]);
    }

    public function sourceName($tableId)
    {
        $tableId = intval($tableId);
        $provider_name = ninja_table_get_data_provider($tableId);

        return $this->sendSuccess([
            'provider_name' => $provider_name
        ]);
    }

    public function processGoogleCSVData($tableId)
    {
        $extra_data = ninjaChartsSanitizeArray($this->request->get('extra_data', []));
        $table_id = intval($tableId);
        $tableRows = ninjaTablesGetTablesDataByID($table_id, $tableColumns = [], $defaultSorting = false, $disableCache = false, 0, $skip = false, $ownOnly = false);

        $uniqueKey = apply_filters('ninja_charts_ntm_google_csv_unique_key', '');

        if (array_key_exists('rows', $extra_data ) && count($tableRows) > 0) {
            $tableColumns = array_keys($tableRows[0]);
            if ($extra_data && ($extra_data['rows']['pick_range'] === 'true' || $extra_data['rows']['pick_date'] === 'true')) {
                $keyExist = $uniqueKey && array_key_exists($uniqueKey, $tableColumns );
                $chart_data = [];

                foreach ($tableRows as $index => $value) {
                    $chart_data[] = (object) [
                        'id' =>  $keyExist ? $value[$uniqueKey] : $index,
                        'value' =>  json_encode($value)
                    ];
                }

                return $this->sendSuccess([
                    'chart_data' =>  $chart_data
                ]);
            }
        }
    }
}
