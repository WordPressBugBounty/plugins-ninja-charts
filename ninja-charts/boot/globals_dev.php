<?php

/**
 * Enable Query Log
 */
if (!function_exists('ninjacharts_eql')) {
    function ninjacharts_eql()
    {
        defined('SAVEQUERIES') || define('SAVEQUERIES', true); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
    }
}

/**
 * Get Query Log
 */
if (!function_exists('ninjacharts_gql')) {
    function ninjacharts_gql()
    {
        $result = [];
        foreach ((array)$GLOBALS['wpdb']->queries as $key => $query) {
            $result[++$key] = array_combine([
                'query', 'execution_time'
            ], array_slice($query, 0, 2));
        }
        return $result;
    }
}

