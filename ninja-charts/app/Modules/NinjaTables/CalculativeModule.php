<?php

namespace NinjaCharts\App\Modules\NinjaTables;

use NinjaCharts\Framework\Support\Arr;

class CalculativeModule extends Module
{
    public function chartData($data)
    {
        extract($data);
        if ($chart_type !== 'bubble' && $chart_type !== 'scatter') {
           return $this->chartyByDataType($data);
        }
    }

    public function chartyByDataType($data)
    {
        $entries = Arr::get($data, 'labels.labels');
        $rows = Arr::get($data, 'tableRows');
        $data_type = $data['keys'][0]['data_type'];
        $submissions = (float) count($rows);
        $processedData = $this->calculate($entries, $submissions, $data_type);

        $data = [ 
            'labels' => Arr::get($processedData, 'labels'),
            'chart_data' => [
                'chart_data' => Arr::get($processedData, 'values')
            ]
        ];

        return $data;
    }

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
