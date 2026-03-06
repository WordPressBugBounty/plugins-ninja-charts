<?php

namespace NinjaCharts\App\Modules\GoogleCharts;

use NinjaCharts\Framework\Support\Arr;

use NinjaCharts\App\Traits\ChartGenerator;
use NinjaCharts\App\Traits\ChartDesignHelper;
use NinjaCharts\App\Modules\NinjaTables\CalculativeModule as NinjaTableCalculative;
use NinjaCharts\App\Modules\FluentForms\CalculativeModule as FluentFormCalculative;
use NinjaCharts\App\Modules\FluentForms\Module;

class GoogleChartModule
{
    use ChartGenerator;
    use ChartDesignHelper;

    public function chartDataFormat($data, $extra_data = [])
    {
        $data_source = Arr::get($data, 'data_source');

        if ($data_source === 'ninja_table') {
            return $this->chartRenderByNinjaTable($data, $extra_data);
        } elseif ($data_source === 'fluent_form') {
            return $this->chartRenderByFluentForm($data, $extra_data);
        } elseif ($data_source === 'manual_inputs') {
            return $this->chartRenderByManualInput($data, $extra_data);
        }
    }

    public function chartRenderByNinjaTable($data, $extra_data)
    {
        $dataType    = Arr::get($data, 'keys.0.data_type');
        $keys        = Arr::get($data, 'keys');
        $tableRows   = Arr::get($data, 'tableRows');
        $ninja_chart = Arr::get($data, 'ninja_chart');

        if ($dataType === 'selection') {
            $chart_datas = (new NinjaTableCalculative())->chartData($data);
            $chart_data  = $this->calculativeDataFormatForNinjaTableAndFluentForm($chart_datas, $ninja_chart);
        } else {
            $chart_data = $this->normalDataFormatForNinjaTableAndFluentForm($tableRows, $keys, $ninja_chart);
        }
        $chart_data = $this->legendFormat($chart_data, $keys, $ninja_chart);

        return apply_filters('ninja_charts_ntm_all_data_by_table', $chart_data);
    }

    public function chartRenderByFluentForm($data, $extra_data)
    {
        $dataType    = Arr::get($data, 'keys.0.data_type');
        $keys        = Arr::get($data, 'keys');
        $tableRows   = Arr::get($data, 'tableRows');
        $ninja_chart = Arr::get($data, 'ninja_chart');

        if ((new Module)->calculativeFields($dataType)) {
            $chart_datas = (new FluentFormCalculative())->chartData($data);
            $chart_data  = $this->calculativeDataFormatForNinjaTableAndFluentForm($chart_datas, $ninja_chart);
        } else {
            $chart_data = $this->normalDataFormatForNinjaTableAndFluentForm($tableRows, $keys, $ninja_chart);
        }
        $chart_data = $this->legendFormat($chart_data, $keys, $ninja_chart);

        return apply_filters('ninja_charts_ffm_data_by_table', $chart_data);
    }

    public function normalDataFormatForNinjaTableAndFluentForm($tableRows, $keys, $ninja_chart)
    {
        $chart_data  = [];
        $label_key   = $this->labelKey($keys);
        $request     = ninjaChartsSanitizeArray($_REQUEST); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $chart_types = ['PieChart', 'DonutChart'];
        $chart_type  = Arr::get($request, 'chart_type') ?: $ninja_chart['chart_type'];

        foreach ($keys as $key => $value) {
            if ($label_key === $value['key']) {
                $this->moveElementByIndex($keys, $key, 0);
                break;
            }
        }
        foreach ($tableRows as $key => $value) {
            $val = [];
            foreach ($keys as $k => $v) {
                $ke   = $v['key'];
                $data = isset($value[$ke]) ? $value[$ke] : null;

                if ((gettype($data) === 'string') && ($data !== null)) {
                    if ($k === 0) {
                        $val[] = (string)$data;
                    } else {
                        if (in_array($chart_type, $chart_types)) {
                            $val[] = ($data != null) ? (float)$data : '';
                        } else {
                            $val[] = ($key == 0 || $data != null) ? (float)$data : 'NaN';
                        }
                    }
                } else {
                    foreach ($value as $kk => $vv) {
                        if (gettype($value[$kk]) === 'array') {
                            if (isset($vv[$ke]) && $vv[$ke] !== null) {
                                if ($k === 0) {
                                    $val[] = (string)$vv[$ke];
                                } else {
                                    if (in_array($chart_type, $chart_types)) {
                                        $val[] = ($vv[$ke] != null) ? (float)$vv[$ke] : '';
                                    } else {
                                        $val[] = ($key == 0 || $vv[$ke] != null) ? (float)$vv[$ke] : 'NaN';
                                    }
                                }
                            }
                        }
                    }
                }
            }
            $chart_data[] = $val;
        }

        return $chart_data;
    }

    public function chartRenderByManualInput($data, $extra_data)
    {
        $manual_inputs = Arr::get($data, 'manual_inputs');
        $ninja_chart   = Arr::get($data, 'ninja_chart');
        $request       = ninjaChartsSanitizeArray($_REQUEST); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $chart_types   = ['PieChart', 'DonutChart'];
        $chart_type    = Arr::get($request, 'chart_type') ?: Arr::get($ninja_chart, 'chart_type');

        $chart_data = [];
        $keys       = array_keys(end($manual_inputs));
        foreach ($manual_inputs as $key => $value) {
            $values = array_values($value);
            $val    = [];
            foreach ($values as $k => $value) {
                if ($k === 0) {
                    $val[] = (string)$value;
                } else {

                    if (in_array($chart_type, $chart_types)) {
                        $val [] = $data != null ? (float)$value : '';
                    } else {
                        $val[] = ($key == 0 || $value != null) ? (float)$value : 'NaN';
                    }
                }
            }
            $chart_data[] = $val;
        }

        if (in_array($chart_type, $chart_types)) {
            return $this->calculativeLegendFormat($ninja_chart, $chart_data);
        } else {
            $first_row = [];
            $series    = Arr::get(
                $request,
                'extra_data.series'
            ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $options   = isset($ninja_chart->options) ? json_decode($ninja_chart->options, true) : '';
            if (isset($series)) {
                array_unshift($series, '');
                foreach ($series as $value) {
                    $first_row[] = isset($value['label']) ? $value['label'] : '';
                }
            } else {
                foreach ($keys as $key => $value) {
                    if ($value === 'text_input') {
                        $first_row[] = '';
                    } else {
                        $r           = isset($options['series']) ? $options['series'][$key - 1] : '';
                        $row         = isset($r['label']) ? $r['label'] : $value;
                        $first_row[] = $row;
                    }
                }
            }
            array_unshift($chart_data, $first_row);

            return $chart_data;
        }
    }

    public function calculativeDataFormatForNinjaTableAndFluentForm($chart_datas, $ninja_chart)
    {
        $chartdata   = $chart_datas['chart_data']['chart_data'];
        $c_labels    = $chart_datas['labels'];
        $request     = ninjaChartsSanitizeArray($_REQUEST); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $chart_types = ['PieChart', 'DonutChart'];
        $chart_type  = Arr::get($request, 'chart_type') ?: $ninja_chart['chart_type'];
        $values      = [];
        $chart_data  = [];
        foreach ($chartdata as $key => $value) {
            $values[] = [
                'label' => $c_labels[$key],
                'value' => $value,
            ];
        }
        foreach ($values as $key => $value) {
            $values = array_values($value);
            $val    = [];
            foreach ($values as $k => $value) {
                if ($k === 0) {
                    $val[] = (string)$value;
                } else {

                    if (in_array($chart_type, $chart_types)) {
                        $val[] = $value != null ? (float)$value : '';
                    } else {
                        $val[] = ($key == 0 || $value != null) ? (float)$value : 'NaN';
                    }
                }
            }
            $chart_data[] = $val;
        }

        return $chart_data;
    }

    public function legendFormat($chart_data, $keys, $ninja_chart)
    {
        if (count($keys) === 1) {
            $chart_data = $this->calculativeLegendFormat($ninja_chart, $chart_data);
        } else {
            $chart_data = $this->otherLegendFormat($keys, $ninja_chart, $chart_data);
        }

        return $chart_data;
    }

    public function calculativeLegendFormat($ninja_chart, $chart_data)
    {
        $chart_types = ['PieChart', 'DonutChart'];
        $request     = ninjaChartsSanitizeArray($_REQUEST); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $chart_type  = Arr::get($request, 'chart_type') ? Arr::get($request, 'chart_type') : $ninja_chart['chart_type'];

        if (in_array($chart_type, $chart_types)) {
            $series  = Arr::get($request, 'extra_data.series');
            $options = isset($ninja_chart->options) ? json_decode($ninja_chart->options, true) : '';

            if ($series != null) {
                $series_data = $series;
            } elseif (isset($options['series'])) {
                $series_data = $options['series'];
            }
            if (isset($series_data)) {
                foreach ($series_data as $key => $value) {
                    if (isset($chart_data[$key]) && is_array($chart_data[$key]) && sizeof($chart_data[$key]) > 1) {
                        $chart_data[$key][0] = isset($value['label']) ? $value['label'] : '';
                    }
                }
            }
        }
        $first_row = ['', ''];
        array_unshift($chart_data, $first_row);

        return $chart_data;
    }

    public function otherLegendFormat($keys, $ninja_chart, $chart_data)
    {
        $chart_types = ['PieChart', 'DonutChart'];
        $request     = ninjaChartsSanitizeArray($_REQUEST); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $chart_type  = Arr::get($request, 'chart_type') ? Arr::get($request, 'chart_type') : $ninja_chart['chart_type'];
        if (in_array($chart_type, $chart_types)) {
            return $this->calculativeLegendFormat($ninja_chart, $chart_data);
        } else {
            $label_key = $this->labelKey($keys);
            $first_row = [];
            $series    = Arr::get(
                $request,
                'extra_data.series'
            ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $options   = isset($ninja_chart->options) ? json_decode($ninja_chart->options, true) : '';
            if (isset($series)) {
                array_unshift($series, '');
                foreach ($series as $value) {
                    $first_row[] = isset($value['label']) ? $value['label'] : '';
                }
            } else {
                foreach ($keys as $key => $value) {
                    if ($label_key === $value['key']) {
                        $first_row[] = '';
                    } else {
                        $r           = isset($options['series'][$key - 1]) ? $options['series'][$key - 1] : '';
                        $row         = isset($r['label']) ? $r['label'] : $value['key'];
                        $first_row[] = $row;
                    }
                }
            }
            array_unshift($chart_data, $first_row);

            return $chart_data;
        }
    }
}
