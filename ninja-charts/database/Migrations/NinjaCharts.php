<?php

namespace NinjaCharts\Database\Migrations;

class NinjaCharts
{
    static $tableName = 'ninja_charts';

    public static function migrate()
    {
        global $wpdb;

        $charsetCollate = $wpdb->get_charset_collate();

        $table = $wpdb->prefix . static::$tableName;
        $indexPrefix = $wpdb->prefix . '_index_';

        $exists = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare('SHOW TABLES LIKE %s', $table)
        );

        if ($exists !== $table) {
            $sql = "CREATE TABLE $table (
            `id` BIGINT(20) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
            `table_id` BIGINT(20) UNSIGNED NULL,
            `options` TEXT NOT NULL,
            `final_keys` VARCHAR(5000) NOT NULL,
            `chart_name` VARCHAR(255) NOT NULL,
            `render_engine` VARCHAR(20) NOT NULL,
            `chart_type` VARCHAR(20) NOT NULL,
            `data_source` VARCHAR(20) NOT NULL,
            `manual_inputs` TEXT NULL,
            `created_at` TIMESTAMP NULL,
            `updated_at` TIMESTAMP NULL,
            INDEX `{$indexPrefix}_table_id_idx` (`table_id` ASC)
        ) $charsetCollate;";

            if (!function_exists('dbDelta')) {
                require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            }
            dbDelta($sql);
        } else {
            self::alterTable($table, $indexPrefix);
        }
    }


    public static function alterTable($table, $indexPrefix)
    {
        global $wpdb;
        $table = esc_sql($table);

        $sql = "ALTER TABLE $table
        MODIFY COLUMN chart_type VARCHAR(20) NOT NULL,
        MODIFY COLUMN chart_name VARCHAR(255) NOT NULL,
        MODIFY COLUMN final_keys VARCHAR(5000) NOT NULL,
        MODIFY COLUMN options TEXT NOT NULL";

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $wpdb->query($sql);
    }
}
