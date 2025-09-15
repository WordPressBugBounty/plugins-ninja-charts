<?php

namespace NinjaCharts\App\Traits;

use NinjaCharts\Framework\Support\Arr;

trait ChartDesignHelper
{
    public function randomColor()
    {
        $c = null;
        for ($i = 0; $i < 6; $i++) {
            $c .= dechex(wp_rand(0, 15));
        }
        return "#$c";
    }

    public function chartBackgroundColor($chart_type, $rows)
    {
        if ($chart_type === 'line') {
            return 'transparent';
        } elseif ($chart_type === 'pie' || $chart_type === 'doughnut' || $chart_type === 'polarArea' || $chart_type === 'funnel') {
            return $this->multipleBackgroundColor($rows);
        } else {
            return $this->randomColor();
        }
    }

    public function multipleBackgroundColor($rows)
    {
        $colors = [];
        foreach ($rows as $row) {
            $colors[] = $this->randomColor();
        }
        return $colors;
    }


    public function dynamicBackgroundColor($ninja_chart = null, $chart_type = null)
    {
        $bg_color = '';
        $color = [];
        if ($ninja_chart !== null) {
            $options = isset($ninja_chart->options) ? json_decode($ninja_chart->options) : '';
            if (isset($options->background_color)) {
                $colors = [];
                foreach ($options->background_color as $key => $value) {
                    $colors[] = $value;
                }
                $color['bg_color'] = $colors;
            }
            if ($chart_type == $ninja_chart->chart_type) {
                $bg_color = $color;
            }
        }
        return $chart_type === 'line' ? $bg_color = 'transparent' : $bg_color;
    }

    public function dynamicBorderColor($ninja_chart = null, $chart_type = null)
    {
        $request = ninjaChartsSanitizeArray($_REQUEST); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $series = Arr::get($request, 'extra_data.series');
        $border_color = '';
        $options = isset($ninja_chart->options) ? json_decode($ninja_chart->options) : '';
        if (isset($series)) {
            $border_color = $series;
        } else {
            if (isset($options->series)) {
                $border_color = $options->series;
            } else {
                $border_color = isset($options->border_color) ? $options->border_color : '';
            }
        }
        return $border_color;
    }

    public function borderColor($ninja_chart, $chart_type, $i)
    {
        if ($chart_type === 'funnel') {
           return 'transparent';
        }

        $border_color = '';
        $bd_color = $this->dynamicBorderColor($ninja_chart, $chart_type);

        if (isset($bd_color[$i]) && gettype($bd_color[$i]) === 'object') {
            $color_label = (array)$bd_color[$i];
        } else {
            $color_label = isset($bd_color[$i]) ? $bd_color[$i] : '';
        }
        $border_color = isset($color_label['color']) ? $color_label['color'] : '';

        if ($border_color) {
            $border_color  = $border_color;
        } else {
            if (isset($bd_color['bd_color'][$i])) {
                $border_color = $bd_color['bd_color'][$i];
            }
        }
        return $border_color;
    }

    public function backgroundColor($ninja_chart, $chart_type, $i)
    {
        $background_color = '';

        $bg_color = $this->dynamicBackgroundColor($ninja_chart, $chart_type);
        $types = ['bar', 'horizontalBar', 'area', 'radar', 'combo'];
        if (in_array($chart_type, $types)) {
            $background_color = $this->borderColor($ninja_chart, $chart_type, $i);
        } else {
            $background_color = isset($bg_color['bg_color'][$i]) ? $bg_color['bg_color'][$i] : '';
        }

        return $background_color;
    }

    public function labelFormat($keys, $tableRows, $chart_type, $extra_data = [])
    {
        $label_key = $this->labelKey($keys);
        $label = array_map(function ($items) use ($label_key) {
            if (isset($items[$label_key])) {
                return $items[$label_key];
            } else {
                $labels = [];
                foreach ($items as $key => $value) {
                    if (gettype($value) === 'array') {
                        $target = $key . '.' . $label_key;
                        if (Arr::get($items, $target) !== null) {
                            $labels[] = isset($items[$key][$label_key]) ? $items[$key][$label_key] : '';
                        }
                    }
                }
                return $labels;
            }
        }, $tableRows);


        return [
            'label_key' => $label_key,
            'labels'    => $label
        ];
    }

    public function piePolarDoughnutLabelFormat($ninja_chart, $chart_type, $new_labels, $c_labels = null, $extra_data = null)
    {
        $chart_types = ['pie', 'polarArea', 'doughnut', 'funnel'];
        $options = isset($ninja_chart->options) ? json_decode($ninja_chart->options, true) : '';
        if (in_array($chart_type, $chart_types)) {
            $labels = [];
            if (isset($extra_data['series'])) {
                foreach ($extra_data['series'] as $key => $value) {
                    $labels[] = $value['label'];
                }
            } elseif (isset($options['series'])) {
                if (($new_labels !== null) && count($new_labels) === count($options['series'])) {
                    foreach ($options['series'] as $value) {
                        $labels[] = $value['label'];
                    }
                } elseif ($new_labels !== null && isset($options['series'])) {
                    foreach ($options['series'] as $value) {
                        $labels[] = $value['label'];
                    }
                } else {
                    foreach ($new_labels as $value) {
                        if (gettype($value) === 'array') {
                            $labels[] = $value[0];
                        } else {
                            $labels[] = $value;
                        }
                    }
                }
            }
            if (in_array($chart_type, $chart_types)) {
                if (count($labels) > 0) {
                    if (($c_labels !== null) && count($c_labels) === count($labels)) {
                        $labels = $labels;
                    } else {
                        if ($c_labels) {
                            $labels = $c_labels;
                        } elseif ($labels) {
                            if (($new_labels !== null) && count($new_labels) === count($labels)) {
                                $labels = $labels;
                            } else {
                                $labels = $new_labels;
                            }
                        } else {
                            $labels = $new_labels;
                        }
                    }
                } else {
                    $labels = $c_labels ? $c_labels : $new_labels;
                }
            } else {
                $labels = $new_labels;
            }
            return $labels;
        } else {
            return $new_labels;
        }
    }

    public function allBackGroundColorFormat($ninja_chart, $chart_type, $rows, $chart_data, $i = 0)
    {
        $background_color = $this->backgroundColor($ninja_chart, $chart_type, $i);
        $chart_types = ['pie', 'polarArea', 'doughnut', 'funnel'];
        $bg_color = [];
        if (in_array($chart_type, $chart_types)) {
            $request = ninjaChartsSanitizeArray($_REQUEST); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $series = Arr::get($request, 'extra_data.series');
            if (isset($series) && $series !== null) {
                foreach ($series as $value) {
                    $bg_color[] =  $value['color'];
                }
            } else {
                if (!isset($request['chart_type'])) {   // condition for frontend
                    $bg_color = $background_color;
                } else {
                    if ($chart_data === 'NULL') {
                        $bg_color = $this->chartBackgroundColor($chart_type, $rows);
                    } else {
                        if ((isset($ninja_chart->data_source)) && $ninja_chart->data_source === 'manual') {
                            if (is_array($background_color) && count($chart_data) === count($background_color)) {
                                $bg_color = $background_color;
                            } else {
                                $bg_color = $this->chartBackgroundColor($chart_type, $rows);
                            }
                        } else {
                            if (is_array($background_color) && is_array($rows) && sizeof($rows) <= sizeof($background_color)) {
                                $bg_color = $background_color;
                            } else {
                                $bg_color = $this->chartBackgroundColor($chart_type, $rows);
                            }
                        }
                    }
                }
            }
        } else {
            $bg_color = $background_color ? $background_color : $this->chartBackgroundColor($chart_type, $rows);
        }
        return $bg_color;
    }

    public function label($ninja_chart, $chart_type, $i)
    {
        $label = '';
        $bd_color = $this->dynamicBorderColor($ninja_chart, $chart_type);

        if (isset($bd_color[$i]) && gettype($bd_color[$i]) === 'object') {
            $color_label = (array)$bd_color[$i];
        } else {
            $color_label = isset($bd_color[$i]) ? $bd_color[$i] : '';
        }

        return isset($color_label['label']) ? $color_label['label'] : '';
    }

    public function lineTension($ninja_chart, $chart_type, $i)
    {
        $request = ninjaChartsSanitizeArray($_REQUEST); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $series = Arr::get($request, 'extra_data.series');
        $line_tension = '0.4';
        $options = isset($ninja_chart->options) ? json_decode($ninja_chart->options) : '';

        if (isset($series)) {
            $line_tension = isset($series[$i]['lineTension']) ? $series[$i]['lineTension'] : '0.4';
        } elseif (isset($options->series)) {
            $series = $options->series;
            $line_tension =  isset($series[$i]->lineTension) ? $series[$i]->lineTension : '0.4';
        }
        return $line_tension;
    }

    public function lineWidth($ninja_chart, $chart_type, $i)
    {
        $request = ninjaChartsSanitizeArray($_REQUEST); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $series = Arr::get($request, 'extra_data.series');
        $line_width = 1;
        $options = isset($ninja_chart->options) ? json_decode($ninja_chart->options) : '';

        if (isset($series)) {
            $line_width = isset($series[$i]['lineWidth']) ? $series[$i]['lineWidth'] : 1;
        } elseif (isset($options->series)) {
            $series = $options->series;
            $line_width =  isset($series[$i]->lineWidth) ? $series[$i]->lineWidth : 1;
        }
        return $line_width;
    }

    public function pointRadius($ninja_chart, $chart_type, $i)
    {
        $request = ninjaChartsSanitizeArray($_REQUEST); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $series = Arr::get($request, 'extra_data.series');
        $pointRadius = '';
        $options = isset($ninja_chart->options) ? json_decode($ninja_chart->options) : '';
        if (isset($series)) {
            $pointRadius = isset($series[$i]['pointRadius']) ? $series[$i]['pointRadius'] : 'false';
        } elseif (isset($options->series)) {
            $series = $options->series;
            $pointRadius =  isset($series[$i]->pointRadius) ? $series[$i]->pointRadius : 'false';
        } else {
            $pointRadius = 3;
        }
        return $pointRadius;
    }

    public function labelKey($keys = [])
    {
        $label_key = '';
        foreach ($keys as $key) {
            if ($label_key === '' && Arr::get($key, 'data_type') === 'text') {
                $label_key = Arr::get($key, 'key');
            }
        }
        foreach ($keys as $key) {
            if ($label_key === '' && Arr::get($key, 'data_type') === 'email') {
                $label_key = Arr::get($key, 'key');
            }
        }
        foreach ($keys as $key) {
            if ($label_key === '' && Arr::get($key, 'data_type') === 'url') {
                $label_key = Arr::get($key, 'key');
            }
        }
        foreach ($keys as $key) {
            if ($label_key === '' && Arr::get($key, 'data_type') === 'date') {
                $label_key = Arr::get($key, 'key');
            }
        }
        $i = 0;
        foreach ($keys as $key) {
            if ($label_key === '' && gettype(Arr::get($keys[$i], 'key')) === 'string') {
                $label_key = Arr::get($key, 'key');
            }
            $i++;
        }

        $l_key = Arr::get($keys[0], 'key');
        if ($label_key) {
            $label_key = $label_key;
        } else {
            $label_key = $l_key;
        }
        return $label_key;
    }

    public function areaChartFill($ninja_chart, $chart_type)
    {
        $request = ninjaChartsSanitizeArray($_REQUEST); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $options = isset($ninja_chart->options) ? json_decode($ninja_chart->options, true) : '';
        $fill = Arr::get($request, 'extra_data.fill');

        if ($chart_type === 'area') {
            if (isset($fill)) {
                $fill = $fill;
            } else {
                $fill = isset($options['chart']['fill']) ? $options['chart']['fill'] : 'origin';
            }
        } else {
            $fill = false;
        }
        return $fill;
    }

    public function moveElementByIndex(&$array, $a, $b) {
        $out = array_splice($array, $a, 1);
        return array_splice($array, $b, 0, $out);
    }

}
