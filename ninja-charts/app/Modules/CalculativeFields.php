<?php

namespace NinjaCharts\App\Modules;

class CalculativeFields
{
    /**
     * This method will decide which fields
     * need calculation for both NT and FF
     * @return field key
     */
    public static function type()
    {
        $fluentFormFields = [
            'radio',
            'checkbox',
            'select',
            'multiple-select',
            'country',
            'ratings',
            'score',
            'promoter_score',
            'method',
            'agreement',

        ];

        $ninjaTableFields = [
            'selection'
        ];

        return array_merge($fluentFormFields, $ninjaTableFields);
    }
}
