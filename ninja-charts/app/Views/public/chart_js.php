<?php use NinjaCharts\Framework\Support\Arr; ?>

<div class="ninja-charts-customize<?php echo esc_attr(Arr::get($chart_keys, 'uniqid')); ?>" >
    <div id="ninja-charts-container"
         class="ninja-charts-chart-js-container"
         style='
                height: <?php echo intval(Arr::get($options, 'chart.height')); ?>px;
                width: <?php echo esc_attr(Arr::get($options, 'chart.responsive')) === 'false' ? intval(Arr::get($options, 'chart.width')) . "px" : 'auto'; ?>;
                background-color: <?php echo esc_attr(Arr::get($options, 'chart.backgroundColor')); ?>;
                border: <?php echo esc_attr(Arr::get($options, 'chart.borderWidth'))."px solid ". esc_attr(Arr::get($options, 'chart.borderColor')); ?> ;
                border-radius: <?php echo esc_attr(Arr::get($options, 'chart.borderRadius')); ?>px;'
         data-id="<?php echo esc_attr(Arr::get($chart_keys, 'id')); ?>"
         data-uniqid="<?php echo esc_attr(Arr::get($chart_keys, 'uniqid')); ?>"
             >
    <canvas
        id= "<?php echo "ninja_charts_instance" .esc_attr( Arr::get($chart_keys, 'uniqid')); ?>"
>
    </canvas>
    </div>
</div>
