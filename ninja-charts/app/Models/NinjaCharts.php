<?php

namespace NinjaCharts\App\Models;

use NinjaCharts\Framework\Support\Arr;
use NinjaCharts\Framework\Database\Orm\Model;

class NinjaCharts extends Model
{
    protected $table = 'ninja_charts';

    public static function store($data)
    {
        do_action('ninja_charts_before_chart_save_or_update', $data);
        $options = Arr::get($data, 'options');
        $datasets = json_decode(Arr::get($data, 'datasets', false), true);

        if ($datasets) {
            foreach ($datasets as $key => $value) {
                $background_color[] = Arr::get($value, 'backgroundColor');
                $border_color[] = Arr::get($value, 'borderColor');
            }
            $options['background_color'] = $background_color;
            $options['border_color'] = $border_color;
        }

        $options['row'] = Arr::get($data, 'row');
        $id = Arr::get($data, 'id');
        $ninja_charts = $id ? NinjaCharts::findOrFail($id) : new NinjaCharts();
        $ninja_charts = self::allRow($ninja_charts, $data, $options, $clone = false);

        do_action('ninja_charts_after_chart_save_or_update', $ninja_charts);
        return $ninja_charts;
    }

    public static function getChartData($keyword = '', $perPage = 10)
    {
        $ninja_charts = NinjaCharts::query()
            ->orderBy('id', 'desc')
            ->where(function ($query) use ($keyword) {
                $query->where('chart_name', 'LIKE', "%{$keyword}%")
                    ->orWhere('render_engine', 'LIKE', "%{$keyword}%")
                    ->orWhere('chart_type', 'LIKE', "%{$keyword}%");
            })->paginate($perPage);
        return $ninja_charts;
    }

    public static function duplicate($id = null)
    {
        do_action('ninja_charts_before_chart_duplicate', $id);
        $data = NinjaCharts::findOrFail($id);
        $ninja_charts = new NinjaCharts();
        $ninja_charts = self::allRow($ninja_charts, $data, $options = [], $clone = true);
        do_action('ninja_charts_after_chart_clone', $ninja_charts);
        return $ninja_charts;
    }

    public static function allRow($ninja_charts, $data, $options, $clone)
    {
        $final_keys = '';
        if (gettype(Arr::get($data, 'final_keys')) === 'string') {
            $final_keys = json_decode(Arr::get($data, 'final_keys'));
        } else {
            $final_keys = (Arr::get($data, 'final_keys'));
        }
        $ninja_charts->table_id = Arr::get($data, 'data_source') === 'manual' ? 0 : Arr::get($data, 'table_id');
        $ninja_charts->chart_name = Arr::get($data, 'chart_name');
        $ninja_charts->render_engine = Arr::get($data, 'render_engine');
        $ninja_charts->chart_type = Arr::get($data, 'chart_type');
        $ninja_charts->data_source = Arr::get($data, 'data_source');
        $ninja_charts->final_keys = $clone ? Arr::get($data, 'final_keys') : json_encode($final_keys);
        $ninja_charts->options = $clone ? Arr::get($data, 'options') : json_encode($options);
        $ninja_charts->manual_inputs = $clone ? Arr::get($data, 'manual_inputs') : json_encode(Arr::get($data, 'manualInputData'));
        $ninja_charts->save();

        return $ninja_charts;
    }

    public static function remove($ids)
    {
        do_action('ninja_charts_before_chart_delete', $ids);
        foreach ($ids as $id) {
            NinjaCharts::findOrFail($id)->delete();
            do_action('ninja_charts_chart_deleted', $id);
        }
    }
}
