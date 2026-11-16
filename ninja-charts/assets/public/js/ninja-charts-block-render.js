/**
 * Ninja Chart (Chart.js) – frontend render for Gutenberg block.
 * Finds .ninja-charts-block-container[data-chart-config], creates Chart.js instance.
 */
(function () {
	'use strict';

	var Device = window.NinjaChartsDevice;

	// The config for one device: the server sends the parts tablets and phones change in
	// config.devices, without the data, which every device shares
	function configForDevice(config, device) {
		var part = config.devices && config.devices[device];
		return part ? Object.assign({}, config, part) : config;
	}

	// Builds the Chart.js config from the server's config, and draws it on the canvas
	function drawChart(canvas, config) {
		// Chart.js keeps state on what it draws, and the code below adds to the options,
		// so each drawing gets its own copy of both
		var copy = JSON.parse(JSON.stringify({ data: config.data, options: config.options || {} }));
		var chartConfig = {
			type: config.type,
			data: {
				labels: copy.data.labels || [],
				datasets: copy.data.datasets || [],
			},
			options: Object.assign(
				{
					responsive: true,
					maintainAspectRatio: false,
				},
				copy.options
			),
		};

		// Add datalabels plugin for pie/doughnut/polarArea/funnel to show percentages
		if (config.useDataLabels && window.ChartDataLabels) {
			chartConfig.plugins = [window.ChartDataLabels];
			chartConfig.options.plugins = chartConfig.options.plugins || {};
			chartConfig.options.plugins.datalabels = {
				display: true,
				// Each value as a share of its own series
				formatter: function (value, context) {
					var data = (context && context.dataset && context.dataset.data) || [];
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
			return new window.Chart(canvas, chartConfig);
		} catch (err) {
			console.warn('Ninja Charts block: Chart.js init error', err);
			return null;
		}
	}

	function initBlockCharts() {
		if (typeof window.Chart === 'undefined') return;

		var device = Device ? Device.currentDevice() : 'desktop';
		// Charts with tablet or phone settings, redrawn when the viewport crosses a breakpoint
		var deviceCharts = [];

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

			var entry = { canvas: canvas, config: config, chart: drawChart(canvas, configForDevice(config, device)) };
			if (config.devices) {
				deviceCharts.push(entry);
			}
		});

		if (Device && deviceCharts.length) {
			Device.onDeviceChange(function (next) {
				deviceCharts.forEach(function (entry) {
					if (entry.chart) entry.chart.destroy();
					entry.chart = drawChart(entry.canvas, configForDevice(entry.config, next));
				});
			});
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initBlockCharts);
	} else {
		initBlockCharts();
	}
})();
