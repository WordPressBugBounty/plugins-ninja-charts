/**
 * Ninja Chart (Chart.js) – frontend render for Gutenberg block.
 * Finds .ninja-charts-block-container[data-chart-config], creates Chart.js instance.
 */
(function () {
	'use strict';

	function initBlockCharts() {
		if (typeof window.Chart === 'undefined') return;

		// Container with dimensions and data-chart-config is .ninja-charts-block-container
		var containers = document.querySelectorAll('.ninja-charts-block-container[data-engine="chart_js"][data-chart-config]');
		containers.forEach(function (el) {
			var configJson = el.getAttribute('data-chart-config');
			if (!configJson) return;

			var config;
			try {
				config = JSON.parse(configJson);
			} catch (e) {
				console.warn('Ninja Charts block: invalid data-chart-config', e);
				return;
			}

			if (!config.type || !config.data) return;

			var canvas = document.createElement('canvas');
			el.appendChild(canvas);

			var chartConfig = {
				type: config.type,
				data: {
					labels: config.data.labels || [],
					datasets: config.data.datasets || [],
				},
				options: Object.assign(
					{
						responsive: true,
						maintainAspectRatio: false,
					},
					config.options || {}
				),
			};

			// Add datalabels plugin for pie/doughnut/polarArea/funnel to show percentages
			if (config.useDataLabels && window.ChartDataLabels) {
				chartConfig.plugins = [window.ChartDataLabels];
				var data = (chartConfig.data.datasets[0] && chartConfig.data.datasets[0].data) || [];
				chartConfig.options.plugins = chartConfig.options.plugins || {};
				chartConfig.options.plugins.datalabels = {
					display: true,
					formatter: function (value) {
						var total = data.reduce(function (a, b) {
							return Number(a) + Number(b);
						}, 0);
						if (total === 0) return '0%';
						var percent = (value / total * 100).toFixed(2);
						// Remove .00 if whole number
						return percent.slice(-2) === '00' ? percent.slice(0, -3) + '%' : percent + '%';
					},
					color: '#ffffff',
					font: {
						size: 14,
						weight: 'normal',
					},
				};
			}

			// Legend box fill: outlined boxes when legendBoxFill is false
			if (config.legendBoxFill === false) {
				chartConfig.options.plugins = chartConfig.options.plugins || {};
				chartConfig.options.plugins.legend = chartConfig.options.plugins.legend || {};
				chartConfig.options.plugins.legend.labels = chartConfig.options.plugins.legend.labels || {};
				chartConfig.options.plugins.legend.labels.generateLabels = function (chart) {
					var typeOverrides = window.Chart.overrides[chart.config.type];
					var generateFn = (typeOverrides && typeOverrides.plugins && typeOverrides.plugins.legend
						&& typeOverrides.plugins.legend.labels && typeOverrides.plugins.legend.labels.generateLabels)
						|| window.Chart.defaults.plugins.legend.labels.generateLabels;
					var original = generateFn(chart);
					return original.map(function (item) {
						return Object.assign({}, item, {
							fillStyle: 'transparent',
							strokeStyle: item.strokeStyle || item.fillStyle,
							lineWidth: 2,
						});
					});
				};
			}

			// Fix legend when area fill is off (backgroundColor is transparent)
			if (config.areaFillOff) {
				var boxFillOff = config.legendBoxFill === false;
				chartConfig.options.plugins = chartConfig.options.plugins || {};
				chartConfig.options.plugins.legend = chartConfig.options.plugins.legend || {};
				chartConfig.options.plugins.legend.labels = chartConfig.options.plugins.legend.labels || {};
				chartConfig.options.plugins.legend.labels.generateLabels = function (chart) {
					var typeOverrides = window.Chart.overrides[chart.config.type];
					var generateFn = (typeOverrides && typeOverrides.plugins && typeOverrides.plugins.legend
						&& typeOverrides.plugins.legend.labels && typeOverrides.plugins.legend.labels.generateLabels)
						|| window.Chart.defaults.plugins.legend.labels.generateLabels;
					var original = generateFn(chart);
					return original.map(function (item) {
						var ds = chart.data.datasets[item.datasetIndex];
						var color = ds ? (ds.borderColor || '#3498db') : item.strokeStyle;
						if (boxFillOff) {
							return Object.assign({}, item, { fillStyle: 'transparent', strokeStyle: color, lineWidth: 2 });
						}
						return Object.assign({}, item, { fillStyle: color });
					});
				};
			}

			// Bubble chart: custom tooltip labels
			if (config.bubbleLabels) {
				chartConfig.options.plugins = chartConfig.options.plugins || {};
				chartConfig.options.plugins.tooltip = chartConfig.options.plugins.tooltip || {};
				chartConfig.options.plugins.tooltip.callbacks = {
					title: function (items) {
						var raw = (items[0] && items[0].raw) || {};
						return raw.label || '';
					},
					label: function (ctx) {
						var raw = ctx.raw || {};
						var pointName = raw.label || ('Point ' + (ctx.dataIndex + 1));
						var bl = config.bubbleLabels;
						return pointName + ': ' + bl.x + ': ' + raw.x + ', ' + bl.y + ': ' + raw.y + ', ' + bl.r + ': ' + raw.r;
					},
				};
			}

			// Scatter chart: custom tooltip labels
			if (config.scatterLabels) {
				chartConfig.options.plugins = chartConfig.options.plugins || {};
				chartConfig.options.plugins.tooltip = chartConfig.options.plugins.tooltip || {};
				chartConfig.options.plugins.tooltip.callbacks = {
					title: function (items) {
						var raw = (items[0] && items[0].raw) || {};
						return raw.label || '';
					},
					label: function (ctx) {
						var raw = ctx.raw || {};
						var pointName = raw.label || ('Point ' + (ctx.dataIndex + 1));
						var sl = config.scatterLabels;
						return pointName + ': ' + sl.x + ': ' + raw.x + ', ' + sl.y + ': ' + raw.y;
					},
				};
			}

			try {
				new window.Chart(canvas, chartConfig);
			} catch (err) {
				console.warn('Ninja Charts block: Chart.js init error', err);
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initBlockCharts);
	} else {
		initBlockCharts();
	}
})();
