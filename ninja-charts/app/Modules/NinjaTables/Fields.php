<?php

namespace NinjaCharts\App\Modules\NinjaTables;

class Fields
{
    /**
     * This method will decide which NinjaTables fields
     * are use for chart generation
     * @return field key
     */
    public static function allowed()
    {
        $types = [
            'text',
            'number',
            'date',
            'selection'
        ];
        return $types;
    }
}
