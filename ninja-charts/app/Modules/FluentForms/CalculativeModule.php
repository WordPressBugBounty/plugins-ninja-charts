<?php

namespace NinjaCharts\App\Modules\FluentForms;

use NinjaCharts\Framework\Support\Arr;
use NinjaCharts\App\Services\CountryName;

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
        if ($data_type === 'checkbox' || $data_type === 'multiple-select') {
            $_submitted = 0;
            foreach ($entries as $value) {
                if (count($value) > 0 && !empty($value[0])) {
                    $_submitted++;
                    foreach ($value as $key => $val) {
                        $entriesArr[] = $val;
                    }
                }
            }
        } else {
            foreach ($entries as $value) {
                if (!empty($value) && !is_array($value)) {
                    $entriesArr[] = $value;
                } elseif (!empty($value[1])) {
                    $entriesArr[] = $value[1];
                } elseif (!empty($value[0])) {
                    $entriesArr[] = $value[0];
                }
            }
        }

        $calculated = array_count_values($entriesArr);

        // Get country full names from sort names
        if ($data_type === 'country') {
            foreach ($calculated as $key => $value) {
                if (isset(CountryName::list()[$key])) {
                    $calculated[CountryName::list()[$key]] = $calculated[$key];
                    unset($calculated[$key]);
                }
            }
        }

        $labels = [];
        $values = [];
        $submitted = 0;
        foreach ($calculated as $key => $val) {
            $labels[] = $key;
            $values[] = $val;
            $submitted = $submitted + $val;
        }

        if ($data_type === 'checkbox' || $data_type === 'multiple-select') {
            if ($submissions !== $_submitted) {
                $labels[] = 'Others';
                $values[] = ($submissions - $_submitted);
            }
        } else {
            if ($submissions != $submitted) {
                if ($data_type === 'ratings' || $data_type === 'promoter_score') {
                    $labels[] = 'Not Rated';
                } else {
                    $labels[] = 'Others';
                }
                $values[] = ($submissions - $submitted);
            }
        }

        return [
            "labels" => $labels,
            "values" => $values
        ];
    }
}
