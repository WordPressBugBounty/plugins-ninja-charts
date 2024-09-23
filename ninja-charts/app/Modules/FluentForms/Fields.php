<?php

namespace NinjaCharts\App\Modules\FluentForms;

class Fields
{
    /**
     * This method will decide which FF-fields
     * are use for chart generation
     * @return field key
     */
    public static function allowed()
    {
        $types = [
            'text',
            'email',
            'number',
            'radio',
            'checkbox',
            'select',
            'multiple-select',
            'date',
            'url',
            'country',
            'ratings',
            'promoter_score',
            'method',
            'rangeslider',
            'picker',
            'phone',
            'quantity_component',
            'agreement'
        ];
        return $types;
    }
}
