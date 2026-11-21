<?php
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<?php use NinjaCharts\Framework\Support\Arr; ?>
<?php use NinjaCharts\App\Helpers\DeviceOptions; ?>
<?php
/**
 * Passed in by ShortCodeController::renderView().
 *
 * @var array $options    the chart's saved options
 * @var array $chart_keys uniqid and id
 */

// Heights set for tablets and phones, applied by media query so the box never jumps.
// The selector is built from the server-made uniqid only.
$ninja_charts_device_css = DeviceOptions::heightCss(
    '.ninja-charts-customize' . preg_replace('/[^A-Za-z0-9_-]/', '', (string) Arr::get($chart_keys, 'uniqid')) . ' .ninja-charts-echarts-container',
    $options
);
?>
<?php if ($ninja_charts_device_css) : ?>
<style><?php echo $ninja_charts_device_css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from intval() heights and a sanitized selector ?></style>
<?php endif; ?>

<div class="ninja-charts-customize<?php echo esc_attr(Arr::get($chart_keys, 'uniqid')); ?>" >
    <div class="ninja-charts-echarts-container"
         style='
                height: <?php echo intval(Arr::get($options, 'chart.height')); ?>px;
                width: <?php echo esc_attr(Arr::get($options, 'chart.responsive')) === 'false' ? intval(Arr::get($options, 'chart.width')) . "px" : 'auto'; ?>;
                max-width: <?php echo esc_attr(intval(Arr::get($options, 'chart.borderWidth')) > 0 ? 'calc(100% - ' . (2 * intval(Arr::get($options, 'chart.borderWidth'))) . 'px)' : '100%'); ?>;
                background-color: <?php echo esc_attr(Arr::get($options, 'chart.backgroundColor')); ?>;
                border: <?php echo esc_attr(Arr::get($options, 'chart.borderWidth'))."px solid ". esc_attr(Arr::get($options, 'chart.borderColor')); ?> ;
                border-radius: <?php echo esc_attr(Arr::get($options, 'chart.borderRadius')); ?>px;'
         data-id="<?php echo esc_attr(Arr::get($chart_keys, 'id')); ?>"
         data-uniqid="<?php echo esc_attr(Arr::get($chart_keys, 'uniqid')); ?>"
         data-nonce="<?php echo esc_attr(wp_create_nonce('ninja_chart_data_' . intval(Arr::get($chart_keys, 'id')))); ?>"
         >
        <div class="ninja-charts-echarts-instance" id="<?php echo 'ninja_charts_instance' . esc_attr(Arr::get($chart_keys, 'uniqid')); ?>" style="width: 100%; height: 100%;"></div>
    </div>
</div>
