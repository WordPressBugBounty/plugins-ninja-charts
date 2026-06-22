<?php

namespace NinjaCharts\Database;

use NinjaCharts\Database\Migrations\NinjaCharts;

class DBMigrator
{
    public static function run($network_wide = false)
    {
        NinjaCharts::migrate();
    }

    /* Test-harness hooks — used by dev/test/inc/RefreshDatabase.php */

    public static function migrateUp()
    {
        if (!function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        static::run();
    }

    public static function migrateDown()
    {
        /* No-op: RefreshDatabase truncates the tables between tests */
    }

    public static function getMigrations(): array
    {
        return ['ninja_charts' => NinjaCharts::class];
    }
}
