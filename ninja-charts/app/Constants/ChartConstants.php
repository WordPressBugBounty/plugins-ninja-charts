<?php

namespace NinjaCharts\App\Constants;

class ChartConstants
{
    // Rendering engines
    const ENGINE_CHART_JS      = 'chart_js';
    const ENGINE_GOOGLE_CHARTS = 'google_charts';

    // Data source identifiers — used in Provider::get() and as data_source markers in data arrays
    const SOURCE_NINJA_TABLE  = 'ninja_table';
    const SOURCE_FLUENT_FORM  = 'fluent_form';
    const SOURCE_MANUAL       = 'manual';
    const SOURCE_MANUAL_DATA  = 'manual_inputs';
}
