<?php use NinjaCharts\Framework\Support\Arr; ?>

<div class="ninja-charts-google-container"
     data-id="<?php echo esc_attr(Arr::get($chart_keys, 'id')); ?>"
     data-uniqid="<?php echo esc_attr(Arr::get($chart_keys, 'uniqid')); ?>"
>
    <div id= "<?php echo esc_attr("ninja_charts_instance" .Arr::get($chart_keys, 'uniqid')); ?>"
         class="<?php echo esc_attr("ninja_charts_instance" .Arr::get($chart_keys, 'uniqid')); ?>"
         style='
             height: <?php echo intval(Arr::get($options, 'chart.height')); ?>px;
             width: <?php echo esc_attr(Arr::get($options, 'chart.responsive')) === 'false' ? intval(Arr::get($options, 'chart.width')) . "px" : 'auto'; ?>;
             '
    ></div>
</div>
