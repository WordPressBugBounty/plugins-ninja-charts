<?php

namespace NinjaCharts\App\Modules;

interface DataSourceInterface
{
    public function getTableList();

    public function getKeysByTable($table_id = null);

    public function getAllDataByTable($table_id = null, $keys = null, $chart_type = null, $extra_data = [], $id = null);

    public function renderChart($data);
}
