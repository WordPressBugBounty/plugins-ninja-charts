<?php

namespace NinjaCharts\Database;

use NinjaCharts\Database\Migrations\NinjaCharts;

class DBMigrator
{
    public static function run($network_wide = false)
    {
        NinjaCharts::migrate();
    }
}
