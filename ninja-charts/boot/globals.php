<?php

/**
 ***** DO NOT CALL ANY FUNCTIONS DIRECTLY FROM THIS FILE ******
 *
 * This file will be loaded even before the framework is loaded
 * so the $app is not available here, only declare functions here.
 */
is_readable(__DIR__ . '/globals_dev.php') && include 'globals_dev.php';

if ($app->config->get('app.env') == 'dev') {
    $ninjaChartsGlobalsDevFile = __DIR__ . '/globals_dev.php';

    is_readable($ninjaChartsGlobalsDevFile) && include $ninjaChartsGlobalsDevFile;
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
        return gmdate('Y-m-d H:i:s');
    }
}

if ( ! function_exists('ninjaChartsDate')) {
    function ninjaChartsDate()
    {
        return gmdate('Y-m-d');
    }
}

if ( ! function_exists('ninjaChartsFormatDate')) {
    function ninjaChartsFormatDate($date)
    {
        return gmdate('d M, Y', strtotime($date));
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

        return "https://www.gravatar.com/avatar/{$hash}?s=128";
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

    return current_user_can('manage_options');
}
