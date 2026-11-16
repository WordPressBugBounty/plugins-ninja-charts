/**
 * Ninja Chart (Google Charts) – frontend render for Gutenberg block.
 * Finds .ninja-charts-block-container[data-engine="google_charts"][data-chart-config],
 * loads Google Charts library and renders.
 */
(function () {
	'use strict';

	// Same rule as NinjaChartsHover.markNoHover (ninja-charts-hover.js), which this page does not load
	var NO_HOVER_CLASS = 'ninja-charts-no-hover';
	function markNoHover(el) {
		el.classList.add(NO_HOVER_CLASS);
		if (document.getElementById('ninja-charts-no-hover-style')) return;
		var style = document.createElement('style');
		style.id = 'ninja-charts-no-hover-style';
		style.textContent = ['0.3', '0.15', '0.05'].map(function (opacity) {
			return '.' + NO_HOVER_CLASS + ' svg [fill="none"][stroke-opacity="' + opacity + '"]';
		}).join(',') + '{display:none}';
		(document.head || document.documentElement).appendChild(style);
	}

	function initGoogleBlockCharts() {
		var containers = document.querySelectorAll(
			'.ninja-charts-block-container[data-engine="google_charts"][data-chart-config]'
		);
		if (!containers.length) return;

		// Ensure Google Charts loader is available
		if (typeof google === 'undefined' || !google.charts) {
			console.warn('Ninja Charts block: Google Charts loader not available');
			return;
		}

		var Device = window.NinjaChartsDevice;
		var device = Device ? Device.currentDevice() : 'desktop';
		// Charts with tablet or phone settings, redrawn when the viewport crosses a breakpoint
		var deviceCharts = [];

		// The options for one device: the server sends the parts tablets and phones change
		// in config.devices, without the data, which every device shares
		function optionsForDevice(config, current) {
			var part = config.devices && config.devices[current];
			return (part && part.chartOptions) || config.chartOptions || {};
		}

		google.charts.load('current', { packages: ['corechart'] });
		google.charts.setOnLoadCallback(function () {
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

				if (!config.chartType || !config.chartData) return;

				var gVis = google.visualization;
				if (!gVis || !gVis[config.chartType]) {
					console.warn('Ninja Charts block: unsupported Google chart type', config.chartType);
					return;
				}

				try {
					// Hover off with tooltips on: hide only Google's highlight outlines
					if (config.hideHover) {
						markNoHover(el);
					}
					var dataTable = gVis.arrayToDataTable(config.chartData);
					var chart = new gVis[config.chartType](el);
					chart.draw(dataTable, optionsForDevice(config, device));
					if (config.devices) {
						deviceCharts.push({ chart: chart, dataTable: dataTable, config: config });
					}
				} catch (err) {
					console.warn('Ninja Charts block: Google Charts init error', err);
				}
			});

			// The media-query CSS has already resized each box, so Google draws to its new size
			if (Device && deviceCharts.length) {
				Device.onDeviceChange(function (next) {
					deviceCharts.forEach(function (entry) {
						entry.chart.draw(entry.dataTable, optionsForDevice(entry.config, next));
					});
				});
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initGoogleBlockCharts);
	} else {
		initGoogleBlockCharts();
	}
})();
