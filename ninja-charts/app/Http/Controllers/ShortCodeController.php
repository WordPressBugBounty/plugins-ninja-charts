<?php

namespace NinjaCharts\App\Http\Controllers;

use NinjaCharts\Framework\Support\Arr;

use NinjaCharts\App\App;
use NinjaCharts\App\Traits\ChartDesignHelper;
use NinjaCharts\App\Models\NinjaCharts;
use NinjaCharts\App\Traits\ChartOption;
use NinjaCharts\App\Modules\Provider;

class ShortCodeController extends Controller
{
    use ChartDesignHelper, ChartOption;

    public function makeShortCode($atts = [], $content = null, $tag = '')
    {
        // normalize attribute keys, lowercase
        $atts = array_change_key_case((array)$atts, CASE_LOWER);
        // override default attributes with user attributes
        $wporg_atts = shortcode_atts([
            'id' => null,
        ], $atts, $tag);

        $id = Arr::get($wporg_atts, 'id');
        $ninjaCharts = NinjaCharts::find($id);
        if ($ninjaCharts) {
            $chart_data = Provider::get($ninjaCharts->data_source)->renderChart($ninjaCharts);
            return $this->renderView($ninjaCharts, $chart_data);
        } else {
            return __("Invalid ShortCode...!", 'ninja_charts');
        }
    }

    public function ninjaChartsShortCode()
    {
        add_shortcode('ninja_charts', [$this, 'makeShortCode']);
    }

    public function renderView($ninjaCharts, $chart_data)
    {
        $app = App::getInstance();
        $ninjaCharts = $this->undefinedChartOptionsAppend($ninjaCharts);
        $ninjaCharts['chart_data'] = $chart_data;
        $options = json_decode(Arr::get($ninjaCharts, 'options'), true);
        $uniqid =  '_' . rand() . '_' . Arr::get($ninjaCharts, 'id');
        $chart_keys = [
            "uniqid"        => $uniqid,
            "id"            => Arr::get($ninjaCharts, 'id')
        ];
        self::addInlineVars($ninjaCharts, 'ninja_charts_instance');
        if ($ninjaCharts->render_engine === 'chart_js') {
            self::chartJsAssets();
            do_action('ninja_charts_shortcode_assets_loaded');
            return $app->view->make('public.chart_js', compact('options', 'chart_keys', 'chart_data'));
        } else if ($ninjaCharts->render_engine === 'google_charts'){
            self::googleChartsAssets();
            do_action('ninja_charts_shortcode_assets_loaded');
            return $app->view->make('public.google_charts', compact('options', 'chart_keys', 'chart_data'));
        }
    }

    private static function addInlineVars($data, $chart_instance_name)
    {
        add_action('wp_footer', function () use ($data, $chart_instance_name) {
            $name = $chart_instance_name . '_' . Arr::get($data, 'id');
            ?>
            <script type="text/javascript">
                window['<?php echo $name; ?>'] = <?php echo $data; ?>
            </script>
            <?php
        });
    }

    private static function chartJsAssets()
    {
        $app = App::getInstance();
        $assets = $app['url.assets'];

        wp_enqueue_script(
            'chartjs',
            $assets . 'public/js/library/chart.umd.js',
            array('jquery'),
            '4.4.2',
            true
        );

        wp_enqueue_script(
            'chartjs_plugin_labels',
            $assets . 'common/js/chartjs-plugin-datalabels.js',
            array('chartjs'),
            '2.0.0',
            true
        );

        wp_enqueue_script(
            'chartjs-chart-funnel',
            $assets . 'public/js/library/chartjs-chart-funnel.umd.min.js',
            array('chartjs'),
            '4.2.0',
            true
        );

        wp_enqueue_script(
            'chart_js_chart_render_js',
            $assets . 'public/js/render.js',
            array('chartjs'),
            '1.0.0',
            true
        );
    }

    private static function googleChartsAssets()
    {
        $app = App::getInstance();
        $assets = $app['url.assets'];

        wp_enqueue_script(
            'googlechart',
            $assets . 'common/js/google-charts.js',
            array('jquery'),
            '1.0.0',
            true
        );
        wp_enqueue_script(
            'google_chart_render_js',
            $assets . 'public/js/google-chart-render.js',
            array('googlechart'),
            '1.0.0',
            true
        );
    }
}
