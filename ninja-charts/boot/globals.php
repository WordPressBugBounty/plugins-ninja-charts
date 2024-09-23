<?php

/**
 ***** DO NOT CALL ANY FUNCTIONS DIRECTLY FROM THIS FILE ******
 *
 * This file will be loaded even before the framework is loaded
 * so the $app is not available here, only declare functions here.
 */
is_readable(__DIR__ . '/globals_dev.php') && include 'globals_dev.php';

if ($app->config->get('app.env') == 'dev') {
    $globalsDevFile = __DIR__ . '/globals_dev.php';

    is_readable($globalsDevFile) && include $globalsDevFile;
}

if ( ! function_exists('dd')) {
    function dd()
    {
        foreach (func_get_args() as $arg) {
            echo "<pre>";
            print_r($arg);
            echo "</pre>";
        }
        die();
    }
}


if ( ! function_exists('ninjaCharts')) {
    function ninjaCharts($module = null)
    {
        return NinjaCharts\App::getInstance($module);
    }
}

if ( ! function_exists('ninjaChartsTimestamp')) {
    function ninjaChartsTimestamp()
    {
        return date('Y-m-d H:i:s');
    }
}

if ( ! function_exists('ninjaChartsDate')) {
    function ninjaChartsDate()
    {
        return date('Y-m-d');
    }
}

if ( ! function_exists('ninjaChartsFormatDate')) {
    function ninjaChartsFormatDate($date)
    {
        return date('d M, Y', strtotime($date));
    }
}

if ( ! function_exists('ninjaChartsGravatar')) {
    /**
     * Get the gravatar from an email.
     *
     * @param string $email
     * @return string
     */
    function ninjaChartsGravatar($email)
    {
        $hash = md5(strtolower(trim($email)));

        return "https://www.gravatar.com/avatar/${hash}?s=128";
    }
}

if ( ! function_exists('ninjaChartsSanitizeArray')) {
    function ninjaChartsSanitizeArray(array $array)
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = ninjaChartsSanitizeArray($value);
            } else {
                $array[$key] = sanitize_text_field($value);
            }
        }

        return $array;
    }
}

function ninjaChartsAdminRole()
{
    if (function_exists('ninja_table_admin_role')) {
        return ninja_table_admin_role();
    }

    return 'manage_options';
}
