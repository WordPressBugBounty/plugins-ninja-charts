<?php
if ( ! defined( 'ABSPATH' ) ) exit;
?>

<?php use NinjaCharts\Framework\Support\Arr; ?>
<?php use NinjaCharts\App\Helpers\DeviceOptions; ?>
<?php
// Heights set for tablets and phones, applied by media query so the box never jumps
$ninja_charts_device_css = DeviceOptions::heightCss(
    '#ninja_charts_instance' . preg_replace('/[^A-Za-z0-9_-]/', '', (string) Arr::get($chart_keys, 'uniqid')),
    $options
);
?>
<?php if ($ninja_charts_device_css) : ?>
<style><?php echo $ninja_charts_device_css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from intval() heights and a sanitized selector ?></style>
<?php endif; ?>

<div class="ninja-charts-google-container"
     data-id="<?php echo esc_attr(Arr::get($chart_keys, 'id')); ?>"
     data-uniqid="<?php echo esc_attr(Arr::get($chart_keys, 'uniqid')); ?>"
     data-nonce="<?php echo esc_attr(wp_create_nonce('ninja_chart_data_' . intval(Arr::get($chart_keys, 'id')))); ?>"
>
    <div id= "<?php echo esc_attr("ninja_charts_instance" .Arr::get($chart_keys, 'uniqid')); ?>"
         class="<?php echo esc_attr("ninja_charts_instance" .Arr::get($chart_keys, 'uniqid')); ?>"
         style='
             height: <?php echo intval(Arr::get($options, 'chart.height')); ?>px;
             width: <?php echo esc_attr(Arr::get($options, 'chart.responsive')) === 'false' ? intval(Arr::get($options, 'chart.width')) . "px" : 'auto'; ?>;
             max-width: 100%;
             '
    ></div>
</div>
