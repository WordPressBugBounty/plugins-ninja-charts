<?php

namespace NinjaCharts\App\Hooks\Handlers;

use NinjaCharts\App\App;
use NinjaCharts\App\Models\NinjaCharts;

class TinyMce
{
    public function addChartsToEditor()
    {
        if (user_can_richedit()) {
            $pages_with_editor_button = array('post.php', 'post-new.php');
            foreach ($pages_with_editor_button as $editor_page) {
                add_action("load-{$editor_page}", array($this, 'initNinjaMceButtons'));
            }
        }
    }

    public function initNinjaMceButtons()
    {
        add_filter("mce_external_plugins", array($this, 'addChartButton'));
        add_filter('mce_buttons', array($this, 'ninjaChartsRegisterButton'));
        add_action('admin_footer', array($this, 'pushNinjaChartsToEditorFooter'));
    }

    public function addChartButton($plugin_array)
    {
        $app = App::getInstance();
        $assets = $app['url.assets'];

        $plugin_array['ninja_charts'] = $assets . 'admin/js/ninja-charts-tinymce-button.js';
        return $plugin_array;
    }

    public function ninjaChartsRegisterButton($buttons)
    {
        array_push($buttons, 'ninja_charts');
        return $buttons;
    }

    public function pushNinjaChartsToEditorFooter()
    {
        $app = App::getInstance();
        $assets = $app['url.assets'];
        $charts = $this->getAllChartsForMce();
        ?>
        <script type="text/javascript">
            window.ninja_charts_tiny_mce = {
                label: '<?php echo esc_js( __( 'Select a chart to insert', 'ninja-charts' ) ); ?>',
                title: '<?php echo esc_js( __( 'Insert Ninja Charts Shortcode', 'ninja-charts' ) ); ?>',
                select_error: '<?php echo esc_js( __( 'Please select a chart', 'ninja-charts' ) ); ?>',
                insert_text: '<?php echo esc_js( __( 'Insert Shortcode', 'ninja-charts' ) ); ?>',
                charts: <?php echo wp_json_encode( $charts ); ?>,
                logo: <?php echo wp_json_encode( $assets . 'images/icon_small.png' ); ?>
            }
        </script>
        <?php
    }

    private function getAllChartsForMce()
    {
        $ninja_charts = NinjaCharts::select('id', 'chart_name')->orderBy('id', 'desc')->get();

        $formatted = array();

        $title = __('Select a Chart', 'ninja-charts');
        if (!$ninja_charts) {
            $title = __('No Charts found. Please add a chart first', 'ninja-charts');
        }

        $formatted[] = array(
            'text'  => $title,
            'value' => ''
        );

        foreach ($ninja_charts as $chart) {
            $formatted[] = [
                'value'   => $chart->id,
                'text' => $chart->chart_name
            ];
        }
        return apply_filters('ninja_charts_editor_available_charts', $formatted);
    }

    public function gutenBlockLoad()
    {
        add_action('enqueue_block_editor_assets', function () {
            $app = App::getInstance();
            $assets = $app['url.assets'];

            wp_enqueue_script(
                'ninja-charts-gutenberg-block',
                $assets . 'admin/js/ninja-charts-gutenblock-build.js',
                array('wp-blocks', 'wp-i18n', 'wp-element', 'wp-components', 'wp-editor'),
                NINJA_CHARTS_VERSION,
                true
            );

            wp_enqueue_style(
                'ninja-charts-gutenberg-block',
                $assets . 'admin/css/gutenblock.css',
                array('wp-edit-blocks'),
                NINJA_CHARTS_VERSION
            );
        });
    }
}
