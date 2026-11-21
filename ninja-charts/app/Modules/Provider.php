<?php

namespace NinjaCharts\App\Modules;

use NinjaCharts\App\Constants\ChartConstants;

class Provider
{
    public static function get($source)
    {
        $allowed = [ChartConstants::SOURCE_NINJA_TABLE, ChartConstants::SOURCE_FLUENT_FORM, ChartConstants::SOURCE_MANUAL];

        if ( ! in_array($source, $allowed, true)) {
            return new \WP_Error(
                'invalid_data_source',
                sprintf(
                    /* translators: %s: data source name */
                    __("Couldn't find %s data provider.", 'ninja-charts'),
                    sanitize_text_field($source)
                )
            );
        }

        if ($source === ChartConstants::SOURCE_NINJA_TABLE && defined('NINJA_TABLES_VERSION')) {
            return new NinjaTables\Module();
        } else if ($source === ChartConstants::SOURCE_FLUENT_FORM && defined('FLUENTFORM_VERSION')) {
            return new FluentForms\Module();
        } else if ($source === ChartConstants::SOURCE_MANUAL) {
            return new ManualModule();
        }

        return new \WP_Error(
            'plugin_not_active',
            sprintf(
                /* translators: %s: data source name */
                __("The required plugin for %s is not active.", 'ninja-charts'),
                sanitize_text_field($source)
            )
        );
    }

    /**
     * The engine for a render_engine value, or null. Kept for callers outside the plugin.
     */
    public static function renderEngine($render_engine)
    {
        return RenderEngineRegistry::get($render_engine);
    }
}
