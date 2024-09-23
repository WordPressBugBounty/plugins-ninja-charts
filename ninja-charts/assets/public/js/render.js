/*eslint-disable*/

jQuery(document).ready(function () {
    (function () {
        let charts = jQuery('.ninja-charts-chart-js-container');
        let ChartDataLabels = window.ChartDataLabels;
        if (charts.length) {
            const th = this;
            charts.each(function () {
                let chartId = jQuery(this).data('id');
                let uniqid = jQuery(this).data('uniqid');
                let chartInstance = 'ninja_charts_instance_' + chartId;
                let canvasDom = 'ninja_charts_instance' + uniqid;
                let renderData = window[chartInstance];
                let options = JSON.parse(renderData.options);
                let canvas = document.getElementById(canvasDom);
                let ctx = canvas.getContext('2d');
                let chartOptions = {
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: options.title.display === 'true',
                            text: renderData.chart_name,
                            position: options.title.position,
                            color: options.title.fontColor,
                            font: {
                                size: Number(options.title.fontSize),
                                style: options.title.fontStyle
                            }
                        },
                        legend: {
                            display: options.legend.display === 'true',
                            position: options.legend.position,
                            labels: {
                                color: options.legend.fontColor
                            }
                        },
                        tooltip: {
                            intersect: true,
                            enabled: options.tooltip.enabled,
                            mode: options.tooltip.mode === 'true' ? 'index' : 'nearest',
                            backgroundColor: options.tooltip.backgroundColor,
                            titleFontSize: 12,
                            titleColor: options.tooltip.titleFontColor,
                            bodyColor: options.tooltip.bodyFontColor,
                            footerColor: options.tooltip.bodyFontColor,
                            footerFontSize: 12,
                            footerAlign: 'right',
                            footerFontStyle: 'normal',
                            bodyFontSize: Number(options.tooltip.bodyFontSize),
                            displayColors: true,
                            borderColor: options.tooltip.borderColor,
                            borderWidth: options.tooltip.borderWidth
                        },
                    },
                    animation: {
                        easing: options.animation ? options.animation : 'linear'
                    },
                    scales: {
                        x: {
                            stacked:  options.axes.stacked === 'true',
                            title: {
                                display: options.axes.display === 'true',
                                text: options.axes.x_axis_label === null ? '' : options.axes.x_axis_label,
                                color: options.chart.fontColor,
                                font: {
                                    size: Number(options.chart.fontSize),
                                    style: options.chart.fontStyle
                                }
                            },
                            grid: {
                                display: options.axes.display === 'true'
                            },
                            ticks: {
                                display: options.axes.display === 'true',
                                color: options.chart.fontColor,
                                font: {
                                    size: Number(options.chart.fontSize),
                                    style: options.chart.fontStyle
                                },
                                beginAtZero: true
                            }
                        },
                        y: {
                            stacked: options.axes.stacked === 'true',
                            title: {
                                stacked: options.axes.stacked === 'true',
                                text: options.axes.y_axis_label === null ? '' : options.axes.y_axis_label,
                                color: options.chart.fontColor,
                                font: {
                                    size: Number(options.chart.fontSize),
                                    style: options.chart.fontStyle
                                }
                            },
                            grid: {
                                display: options.axes.display === 'true'
                            },
                            ticks: {
                                display: options.axes.display === 'true',
                                color: options.chart.fontColor,
                                font: {
                                    size: Number(options.chart.fontSize),
                                    style: options.chart.fontStyle
                                },
                                beginAtZero: true,
                            },
                            min: parseInt(options.axes.verticle_min_tick) ? parseInt(options.axes.verticle_min_tick) : null,
                            max: parseInt(options.axes.verticle_max_tick) ? parseInt(options.axes.verticle_max_tick) : null
                        },
                    },
                    layout: {
                        padding: {
                            left: options.layout.padding.left,
                            right: options.layout.padding.right,
                            top: options.layout.padding.top,
                            bottom: options.layout.padding.bottom
                        }
                    }
                };

                if (chartOptions.scales.y) {
                    if (!options.axes.verticle_min_tick || isNaN(options.axes.verticle_min_tick)) {
                        delete chartOptions.scales.y.ticks.min;
                    } else {
                        chartOptions.scales.y.ticks.min = parseInt(options.axes.verticle_min_tick);
                    }
                    if (!options.axes.verticle_max_tick || isNaN(options.axes.verticle_max_tick)) {
                        delete chartOptions.scales.y.ticks.max;
                    } else {
                        chartOptions.scales.y.ticks.max = parseInt(options.axes.verticle_max_tick);
                    }
                }

                if (options.chart.responsive === 'false') {
                    let marginStyle = {
                        'margin-left': 'auto',
                        'margin-right': 'auto'
                    };

                    if (options.chart.position === 'right') {
                        marginStyle['margin-right'] = '0';
                    } else if (options.chart.position === 'left') {
                        marginStyle['margin-left'] = '0';
                    }

                    let uniqChart = `.ninja-charts-customize${uniqid} .ninja-charts-chart-js-container`;
                    jQuery(uniqChart).css(marginStyle);
                }

                let chartType = renderData.chart_type;
                if (chartType === 'area') {
                    chartType = 'line';
                } else if (chartType === 'combo') {
                    chartType = 'bar';
                } else if (chartType === 'horizontalBar') {
                    chartType = 'bar';
                    chartOptions.indexAxis = 'y';
                } else if (chartType === 'funnel') {
                    chartOptions.indexAxis = 'y';
                    chartOptions.plugins.legend.display = false;
                }

                let config = {
                    type: chartType,
                    data: renderData.chart_data,
                    options: chartOptions
                };

                const labelsChartTypes = ['pie', 'doughnut', 'polarArea', 'funnel'];
                // const calculativeData = ['radio', 'checkbox', 'select', 'selection', 'multiple-select', 'country'];

                if (labelsChartTypes.includes(chartType)) {
                    const data = renderData.chart_data.datasets[0].data;
                    config.plugins = [ChartDataLabels];
                    config.options.plugins.datalabels = {
                        display: true,
                        formatter: (value, context) => {
                            const total = data.reduce((a, b) => Number(a) + Number(b), 0);
                            const res = (value / total * 100).toFixed(2) + '%';
                            return res.split('.')[1] === '00%' ? res.split('.')[0] + '%' : res;
                        },
                        color: '#ffffff',
                        font: {
                            size: 14,
                            weight: 'normal'
                        }
                    }
                }

                new Chart(ctx, config);
                // window[chartInstance] = new Chart(ctx, config);
            })
        }
    })();
});
