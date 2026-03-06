/**
 * Ninja Chart (Google Charts) – frontend render for Gutenberg block.
 * Finds .ninja-charts-block-container[data-engine="google_charts"][data-chart-config],
 * loads Google Charts library and renders.
 */
(function () {
	'use strict';

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
					var dataTable = gVis.arrayToDataTable(config.chartData);
					var chart = new gVis[config.chartType](el);
					chart.draw(dataTable, config.chartOptions || {});
				} catch (err) {
					console.warn('Ninja Charts block: Google Charts init error', err);
				}
			});
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initGoogleBlockCharts);
	} else {
		initGoogleBlockCharts();
	}
})();
