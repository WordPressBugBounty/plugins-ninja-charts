/*eslint-disable*/

jQuery(document).ready(function () {
    (function () {
        let charts = jQuery('.ninja-charts-chart-js-container');
        let ChartDataLabels = window.ChartDataLabels;
        if (charts.length) {
            const th = this;
            charts.each(async function () {
                let chartId = jQuery(this).data('id');
                let uniqid = jQuery(this).data('uniqid');
                let chartNonce = jQuery(this).data('nonce');
                let canvasDom = 'ninja_charts_instance' + uniqid;
                let chartElement = jQuery(this);
                let renderData = null;

                window.NinjaChartsLoader.show(chartElement);

                try {
                    renderData = await jQuery.ajax({
                        url: window.chartJSPublic.ajax_url,
                        type: 'GET',
                        data: {
                            action: 'ninja_charts_get_data',
                            chart_id: chartId,
                            nonce: chartNonce,
                        }
                    });

                    if (!renderData || !renderData.success || !renderData.chart_data || !renderData.chart_data.datasets) {
                        console.error('Invalid chart data received:', renderData);
                        window.NinjaChartsLoader.hide(chartElement);
                        chartElement.empty().append(jQuery('<p>', { css: { color: 'red', padding: '20px' }, text: renderData?.message || 'Failed to load chart data.' }));
                        return;
                    }
                } catch (error) {
                    let errorMessage = 'Failed to load chart data.';

                    if (error.responseJSON && error.responseJSON.message) {
                        errorMessage = error.responseJSON.message;
                    }

                    console.error('Failed to load chart data:', errorMessage);
                    window.NinjaChartsLoader.hide(chartElement);
                    chartElement.empty().append(jQuery('<p>', { css: { color: 'red', padding: '20px' }, text: errorMessage }));
                    return;
                }

                window.NinjaChartsLoader.hide(chartElement);
                
                let options = renderData.options;
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
                            enabled: options.tooltip.enabled === 'true',
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
                            borderWidth: options.tooltip.borderWidth,
                            callbacks: {
                                title: function (context) {
                                    return context[0].label;
                                },
                                label: function (context) {
                                    // Handle polar charts which have object data with 'r' property
                                    if (context.parsed && typeof context.parsed === 'object' && context.parsed.r !== undefined) {
                                        return context.dataset.label + ': ' + context.parsed.r;
                                    }
                                    
                                    // Handle bar/line charts which have object data with 'y' property
                                    if (context.parsed && typeof context.parsed === 'object' && context.parsed.y !== undefined) {
                                        return context.dataset.label + ': ' + context.parsed.y;
                                    }
                                    
                                    // Handle simple parsed values
                                    if (context.parsed !== undefined && context.parsed !== null && typeof context.parsed !== 'object') {
                                        return context.dataset.label + ': ' + context.parsed;
                                    }
                                    
                                    // Handle raw data (for bar charts and other charts)
                                    if (context.raw !== null) {
                                        return context.dataset.label + ': ' + context.raw;
                                    }
                                    
                                    // Fallback
                                    return context.dataset.label + ': 0';
                                }
                            }
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
                                precision: 2,
                            },
                            min: parseInt(options.axes.vertical_min_tick || options.axes.verticle_min_tick) || null,
                            max: parseInt(options.axes.vertical_max_tick || options.axes.verticle_max_tick) || null
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

                const exportMode  = new URLSearchParams(window.location.search).get('ninja_chart_export');
                const isExportMode = exportMode === 'png' || exportMode === 'pdf';

                if (isExportMode) {
                    chartOptions.animation = false;
                }

                let config = {
                    type: chartType,
                    data: renderData.chart_data,
                    options: chartOptions
                };


                if (renderData.options && renderData.chart_data.datasets) {
                    let options = renderData.options;
                    options = (typeof options) === 'string' ? JSON.parse(options) : options;

                    if (options.series && renderData.chart_data.datasets) {
                        options.series.forEach((series, index) => {
                            if (renderData.chart_data.datasets[index]) {
                                if (chartType === 'line' || chartType === 'area') {
                                    const pointRadius = series.pointRadius || 2;
                                    renderData.chart_data.datasets[index].pointRadius = pointRadius;
                                    renderData.chart_data.datasets[index].pointHoverRadius = pointRadius;
                                    renderData.chart_data.datasets[index].pointBackgroundColor = series.color;
                                    renderData.chart_data.datasets[index].pointBorderColor = series.color;
                                    renderData.chart_data.datasets[index].pointBorderWidth = 2;
                                }
                            }
                        });
                    }
                }

                const labelsChartTypes = ['pie', 'doughnut', 'polarArea', 'bar', 'funnel'];
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

                if (isExportMode) {
                    const exportCanvas = document.createElement('canvas');
                    exportCanvas.width  = canvas.width;
                    exportCanvas.height = canvas.height;
                    const exportCtx = exportCanvas.getContext('2d');
                    exportCtx.fillStyle = '#ffffff';
                    exportCtx.fillRect(0, 0, exportCanvas.width, exportCanvas.height);
                    exportCtx.drawImage(canvas, 0, 0);

                    const chartName = renderData.chart_name || 'chart';

                    if (exportMode === 'png') {
                        exportCanvas.toBlob(function (blob) {
                            const url = URL.createObjectURL(blob);
                            const a   = document.createElement('a');
                            a.href     = url;
                            a.download = chartName + '.png';
                            document.body.appendChild(a);
                            a.click();
                            document.body.removeChild(a);
                            URL.revokeObjectURL(url);
                            setTimeout(function () { window.close(); }, 1000);
                        }, 'image/png');
                    } else if (exportMode === 'pdf') {
                        const imgData     = exportCanvas.toDataURL('image/png', 1.0);
                        const w           = exportCanvas.width  * 0.75; // px → pt (96dpi: 1px = 0.75pt)
                        const h           = exportCanvas.height * 0.75;
                        const orientation = w >= h ? 'landscape' : 'portrait';
                        function generatePdf() {
                            const { jsPDF } = window.jspdf;
                            const doc       = new jsPDF({ orientation: orientation, unit: 'pt', format: [w, h] });
                            doc.addImage(imgData, 'PNG', 0, 0, w, h);
                            doc.save(chartName + '.pdf');
                            setTimeout(function () { window.close(); }, 1000);
                        }
                        if (window.jspdf) {
                            generatePdf();
                        } else {
                            var jspdfScript   = document.createElement('script');
                            jspdfScript.src   = window.chartJSPublic.jspdf_url;
                            jspdfScript.onload = generatePdf;
                            document.head.appendChild(jspdfScript);
                        }
                    }
                }
            })
        }
    })();
});
