<?php

namespace NinjaCharts\App\Traits;

use NinjaCharts\Framework\Support\Arr;

trait CalculativeChartTrait
{
    public function chartData($data)
    {
        $chart_type = Arr::get($data, 'chart_type');

        if ($chart_type !== 'bubble' && $chart_type !== 'scatter') {
            return $this->chartyByDataType($data);
        }
    }

    public function chartyByDataType($data)
    {
        $entries       = Arr::get($data, 'labels.labels');
        $rows          = Arr::get($data, 'tableRows');
        $data_type     = Arr::get($data, 'keys.0.data_type');
        $submissions   = (float) count($rows);
        $processedData = $this->calculate($entries, $submissions, $data_type);

        return [
            'labels'     => Arr::get($processedData, 'labels'),
            'chart_data' => [
                'chart_data' => Arr::get($processedData, 'values')
            ]
        ];
    }
}
