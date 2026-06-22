<?php

namespace NinjaCharts\App\Http\Controllers;

if ( ! defined( 'ABSPATH' ) ) exit;

use NinjaCharts\App\App;
use NinjaCharts\App\Traits\ChartDesignHelper;
use NinjaCharts\App\Models\NinjaCharts;
use NinjaCharts\App\Traits\ChartOption;
use NinjaCharts\App\Modules\Provider;
use NinjaCharts\Framework\Support\Arr;
use NinjaCharts\App\Constants\ChartConstants;

class ShortCodeController extends Controller
{
    use ChartDesignHelper, ChartOption;

    public function __construct()
    {
        add_action('wp_ajax_ninja_charts_get_data', [$this, 'getChartData']);
        add_action('wp_ajax_nopriv_ninja_charts_get_data', [$this, 'getChartData']);
    }

    public function getChartData()
    {
        if (sanitize_text_field(Arr::get($_SERVER, 'REQUEST_METHOD')) !== 'GET') {
            wp_send_json([
                'success' => false,
                'message' => __('Invalid request method', 'ninja-charts')
            ], 405);
        }

        // Rate limit: max 60 requests per minute per IP.
        $ip   = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        $rk   = 'ninja_charts_rate_' . md5($ip);
        $hits = (int) get_transient($rk);
        if ($hits >= 60) {
            wp_send_json([
                'success' => false,
                'message' => __('Too many requests', 'ninja-charts')
            ], 429);
        }
        set_transient($rk, $hits + 1, 60);

        $chartId = isset($_GET['chart_id']) ? intval($_GET['chart_id']) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        if ( ! $chartId) {
            wp_send_json([
                'success' => false,
                'message' => __('Chart ID is required', 'ninja-charts')
            ], 400);
        }

        // Nonce is bound to the specific chart ID so that a nonce from one chart page
        // cannot be used to enumerate other chart IDs.
        $nonce = sanitize_text_field(Arr::get($_GET, 'nonce')); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (empty($nonce) || ! wp_verify_nonce($nonce, 'ninja_chart_data_' . $chartId)) {
            wp_send_json([
                'success' => false,
                'message' => __('Security check failed', 'ninja-charts')
            ], 400);
        }

        try {
            $ninjaChart = NinjaCharts::findOrFail($chartId);
            $provider = Provider::get($ninjaChart->data_source);
            if (is_wp_error($provider)) {
                wp_send_json([
                    'success' => false,
                    'message' => $provider->get_error_message()
                ], 400);
            }
            $chart_data = $provider->renderChart($ninjaChart);
            if ($chart_data === null) {
                wp_send_json([
                    'success' => false,
                    'message' => __('No chart data could be generated for the given configuration', 'ninja-charts')
                ], 422);
            }
            $ninjaCharts = $ninjaChart->toArray();

            wp_send_json([
                'success' => true,
                'chart_data' => $chart_data,
                'chart_name' => Arr::get($ninjaCharts, 'chart_name'),
                'chart_type' => Arr::get($ninjaCharts, 'chart_type'),
                'options'    => json_decode(Arr::get($ninjaCharts, 'options'), true)
            ], 200);
        } catch (\Exception $e) {
            wp_send_json([
                'success' => false,
                'message' => __('Chart not found', 'ninja-charts')
            ], 404);
        }
    }


    public function makeShortCode($atts = [], $content = null, $tag = '')
    {
        // normalize attribute keys, lowercase
        $atts = array_change_key_case((array)$atts, CASE_LOWER);
        // override default attributes with user attributes
        $wporg_atts = shortcode_atts([
            'id' => null,
        ], $atts, $tag);

        $id = intval(Arr::get($wporg_atts, 'id'));
        $ninjaCharts = NinjaCharts::find($id);
        if ($ninjaCharts) {
            return $this->renderView($ninjaCharts);
        } else {
            return __("Invalid ShortCode...!", 'ninja-charts');
        }
    }

    public function ninjaChartsShortCode()
    {
        add_shortcode('ninja_charts', [$this, 'makeShortCode']);
    }

    public function renderView($ninjaCharts)
    {
        $app = App::getInstance();
        $ninjaCharts = $this->undefinedChartOptionsAppend($ninjaCharts);
        $options = json_decode(Arr::get($ninjaCharts, 'options'), true);
        $uniqid =  '_' . wp_rand() . '_' . Arr::get($ninjaCharts, 'id');
        $chart_keys = [
            "uniqid"        => $uniqid,
            "id"            => Arr::get($ninjaCharts, 'id')
        ];

        self::enqueueLoaderScript();

        if ($ninjaCharts->render_engine === ChartConstants::ENGINE_CHART_JS) {
            self::chartJsAssets();
            do_action('ninja_charts_shortcode_assets_loaded');
            return $app->view->make('public.chart_js', compact('options', 'chart_keys'));
        } else if ($ninjaCharts->render_engine === ChartConstants::ENGINE_GOOGLE_CHARTS){
            self::googleChartsAssets();
            do_action('ninja_charts_shortcode_assets_loaded');
            return $app->view->make('public.google_charts', compact('options', 'chart_keys'));
        }
    }

    private static function enqueueLoaderScript()
    {
        $app    = App::getInstance();
        $assets = $app['url.assets'];

        wp_enqueue_script(
            'ninja_charts_loader',
            $assets . 'common/js/chart-loader.js',
            array('jquery'),
            NINJA_CHARTS_VERSION,
            true
        );
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

        // Registered only — loaded on demand by render.js when a PDF export is triggered.
        wp_register_script(
            'ninja_charts_jspdf',
            $assets . 'public/js/library/jspdf.umd.min.js',
            array(),
            '2.5.1',
            true
        );

        wp_enqueue_script(
            'chart_js_chart_render_js',
            $assets . 'public/js/render.js',
            array('chartjs', 'ninja_charts_loader'),
            NINJA_CHARTS_VERSION,
            true
        );

        wp_localize_script('chart_js_chart_render_js', 'chartJSPublic', [
            'ajax_url'  => admin_url('admin-ajax.php'),
            'jspdf_url' => $assets . 'public/js/library/jspdf.umd.min.js',
        ]);
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
            array('googlechart', 'ninja_charts_loader'),
            NINJA_CHARTS_VERSION,
            true
        );

        wp_localize_script('google_chart_render_js', 'googleChartPublic', [
            'ajax_url' => admin_url('admin-ajax.php'),
        ]);
    }
}
