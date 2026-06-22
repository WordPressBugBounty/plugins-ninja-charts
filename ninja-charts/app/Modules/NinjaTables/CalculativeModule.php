<?php

namespace NinjaCharts\App\Modules\NinjaTables;

use NinjaCharts\App\Traits\CalculativeChartTrait;

class CalculativeModule extends Module
{
    use CalculativeChartTrait;

    public function calculate($entries, $submissions, $data_type)
    {
        $entriesArr = [];

        foreach ($entries as $value) {
            if (is_array($value) && count($value) > 0 && !empty($value[0])) {
                foreach ($value as $key => $val) {
                    $entriesArr[] = $val;
                }
            } else {
                if (!empty($value)) {
                    $entriesArr[] = $value;
                }
            }
        }

        $calculated = array_count_values($entriesArr);

        $labels = [];
        $values = [];

        foreach ($calculated as $key => $val) {
            $labels[] = $key;
            $values[] = $val;
        }

        return [
            "labels" => $labels,
            "values" => $values
        ];
    }
}
