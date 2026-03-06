<?php

namespace NinjaCharts\App\Blocks\Engines;

defined('ABSPATH') || exit;

use NinjaCharts\Framework\Support\Arr;


/**
 * Chart.js engine – builds Chart.js config and enqueues Chart.js scripts.
 */
class ChartJsEngine implements ChartEngineInterface {

	/**
	 * {@inheritDoc}
	 */
	public static function id(): string {
		return 'chart_js';
	}

	/**
	 * {@inheritDoc}
	 */
	public static function renderElement(): string {
		return 'canvas';
	}

	/**
	 * {@inheritDoc}
	 */
	public static function buildChartConfig(array $attributes, array $options, string $chartType): array {
		$type = $chartType;
		if ($type === 'horizontalBar') $type = 'bar';
		if ($type === 'area')          $type = 'line';

		$chart_opts = Arr::get($options, 'chart');

		// Apply area fill option (default: disabled)
		$area_fill = Arr::get($chart_opts, 'areaFill', false);
		$area_fill = $area_fill === true || $area_fill === 'true';
		// Apply point fill option (default: enabled)
		$point_fill = Arr::get($chart_opts, 'pointFill', true);
		$point_fill = $point_fill !== false && $point_fill !== 'false';
		// Line controls
		$line_width = (int) Arr::get($chart_opts, 'lineWidth', 3) ?: 3;
		$straight_line = Arr::get($chart_opts, 'straightLine', false);
		$straight_line = $straight_line === true || $straight_line === 'true';
		$show_points = Arr::get($chart_opts, 'showPoints', true);
		$show_points = $show_points !== false && $show_points !== 'false';

		$datasets = Arr::get($attributes, 'datasets');
		$processed_datasets = [];

		foreach ($datasets as $ds) {
			$dataset = $ds;
			$ds_color = Arr::get($ds, 'borderColor') ?: Arr::get($ds, 'backgroundColor', '#3498db');

			// Apply areaFill option
			if (!$area_fill && in_array($chartType, ['bar', 'horizontalBar'], true)) {
				$dataset['backgroundColor'] = 'transparent';
				$dataset['borderColor'] = $ds_color;
				$dataset['borderWidth'] = $line_width;
			}
			if (in_array($chartType, ['line', 'area', 'radar'], true)) {
				$dataset['fill'] = $area_fill;
				$dataset['borderWidth'] = $line_width;
				$dataset['tension'] = $straight_line ? 0 : 0.4;
				$point_style = Arr::get($chart_opts, 'pointStyle', 'circle');
				if (!$show_points) {
					$dataset['pointRadius'] = 0;
				} elseif ($point_style && $point_style !== 'circle') {
					$dataset['pointStyle'] = $point_style;
				}
				if (!$area_fill) {
					$dataset['backgroundColor'] = 'transparent';
					$dataset['borderColor'] = $ds_color;
				}
				// Point fill is independent of area fill
				if ($point_fill) {
					$dataset['pointBackgroundColor'] = $ds_color;
				} else {
					$dataset['pointBackgroundColor'] = 'transparent';
					$dataset['pointBorderColor'] = $ds_color;
					$dataset['pointBorderWidth'] = 2;
				}
			}

			$processed_datasets[] = $dataset;
		}

		$config = [
			'type'    => $type,
			'data'    => [
				'labels'   => $attributes['labels'],
				'datasets' => $processed_datasets,
			],
			'options' => self::blockOptionsToChartJsConfig($options, $chartType),
		];

		if ($chartType === 'horizontalBar') {
			$config['options']['indexAxis'] = 'y';
		}

		// Add datalabels config for pie/doughnut/polarArea/funnel to show percentages
		$data_labels_enabled = Arr::get($chart_opts, 'showDataLabels', true);
		$data_labels_enabled = $data_labels_enabled !== false && $data_labels_enabled !== 'false';
		$show_datalabels = in_array($chartType, ['pie', 'doughnut', 'polarArea', 'funnel', 'bar', 'horizontalBar'], true) && $data_labels_enabled;
		if ($show_datalabels) {
			$config['useDataLabels'] = true;
		}

		// Legend box fill (outlined boxes when false)
		$legend_opts  = Arr::get($options, 'legend');
		$legend_box_fill = Arr::get($legend_opts, 'boxFill', false);
		if ($legend_box_fill === false || $legend_box_fill === 'false') {
			$config['legendBoxFill'] = false;
		}

		// Flag for frontend: area fill off needs legend fix
		if (!$area_fill && in_array($chartType, ['bar', 'horizontalBar', 'line', 'area', 'radar'], true)) {
			$config['areaFillOff'] = true;
		}

		// Bubble tooltip labels (callbacks can't be serialized, so pass data for frontend JS)
		if ($chartType === 'bubble') {
			$axes_opts = Arr::get($options, 'axes');
			$config['bubbleLabels'] = [
				'x' => trim(Arr::get($axes_opts, 'x_axis_label', '')) ?: 'X',
				'y' => trim(Arr::get($axes_opts, 'y_axis_label', '')) ?: 'Y',
				'r' => trim(Arr::get($axes_opts, 'bubble_r_label', '')) ?: 'Size',
			];
		}

		// Scatter tooltip labels
		if ($chartType === 'scatter') {
			$axes_opts = Arr::get($options, 'axes');
			$config['scatterLabels'] = [
				'x' => trim(Arr::get($axes_opts, 'x_axis_label', '')) ?: 'X',
				'y' => trim(Arr::get($axes_opts, 'y_axis_label', '')) ?: 'Y',
			];
		}

		return $config;
	}

	/**
	 * {@inheritDoc}
	 */
	public static function enqueueFrontendAssets(string $assetUrl, string $version): void {
		wp_enqueue_script('chartjs', $assetUrl . 'public/js/library/chart.umd.js', [ 'jquery' ], $version, true);
		wp_enqueue_script('chartjs_plugin_labels', $assetUrl . 'common/js/chartjs-plugin-datalabels.js', [ 'chartjs' ], $version, true);
		wp_enqueue_script('chartjs-chart-funnel', $assetUrl . 'public/js/library/chartjs-chart-funnel.umd.min.js', [ 'chartjs' ], $version, true);
		wp_enqueue_script('ninja-charts-block-frontend');
	}

	/*
	|--------------------------------------------------------------------------
	| Chart.js config builder
	|--------------------------------------------------------------------------
	*/

	/**
	 * Map block options to Chart.js 4 config options.
	 */
	private static function blockOptionsToChartJsConfig($options, $chart_type) {
		$title   = Arr::get($options, 'title');
		$legend  = Arr::get($options, 'legend');
		$tooltip = Arr::get($options, 'tooltip');
		$axes    = Arr::get($options, 'axes');
		$layout  = Arr::get($options, 'layout');

		$axes_display  = self::bool(Arr::get($axes, 'display', true));
		$axes_stacked  = self::bool(Arr::get($axes, 'stacked', false));
		$tooltip_border = Arr::get($tooltip, 'border');

		$opts = [
			'responsive'         => true,
			'maintainAspectRatio' => false,
			'plugins'            => [
				'title'   => [
					'display'  => self::bool(Arr::get($title, 'display', true)),
					'text'     => Arr::get($title, 'text', ''),
					'position' => Arr::get($title, 'position', 'top'),
					'color'    => Arr::get($title, 'fontColor', '#000000') ?: '#000000',
					'font'     => [
						'size'  => (int) Arr::get($title, 'fontSize', 16),
						'style' => Arr::get($title, 'fontStyle', 'normal'),
					],
				],
				'legend'  => [
					'display'  => $chart_type !== 'funnel' && self::bool(Arr::get($legend, 'display', true)),
					'position' => Arr::get($legend, 'position', 'top'),
					'labels'   => [
						'color' => Arr::get($legend, 'fontColor', '#000000') ?: '#000000',
						'font'  => [ 'size' => (int) Arr::get($legend, 'fontSize', 12) ],
					],
				],
				'tooltip' => [
					'enabled'         => self::bool(Arr::get($tooltip, 'enabled', true)),
					'mode'            => self::bool(Arr::get($tooltip, 'mode', false)) ? 'index' : 'nearest',
					'backgroundColor' => Arr::get($tooltip, 'backgroundColor', '#ffffff') ?: '#ffffff',
					'titleColor'      => Arr::get($tooltip, 'titleFontColor', '#000000') ?: '#000000',
					'titleFont'       => [ 'size' => (int) Arr::get($tooltip, 'titleFontSize', 12) ],
					'bodyColor'       => Arr::get($tooltip, 'bodyFontColor', '#000000') ?: '#000000',
					'bodyFont'        => [ 'size' => (int) Arr::get($tooltip, 'bodyFontSize', 12) ],
					'borderColor'     => Arr::get($tooltip_border, 'color', '#666666') ?: '#666666',
					'borderWidth'     => (int) Arr::get($tooltip_border, 'width', 1),
				],
			],
			'animation' => [
				'easing' => Arr::get($options, 'animation', 'linear'),
			],
			'layout' => [
				'padding' => self::parsePadding(Arr::get($layout, 'padding', [])),
			],
		];

		$no_axes = in_array($chart_type, [ 'pie', 'doughnut', 'polarArea', 'radar', 'funnel' ], true);
		if (! $no_axes) {
			$x_label = Arr::get($axes, 'x_axis_label', '');
			$y_label = Arr::get($axes, 'y_axis_label', '');
			$min     = Arr::get($axes, 'vertical_min_tick');
			$max     = Arr::get($axes, 'vertical_max_tick');

			$x_scale = [
				'stacked' => $axes_stacked,
				'title'   => [ 'display' => true, 'text' => $x_label ],
				'grid'    => [ 'display' => $axes_display ],
				'ticks'   => [ 'display' => true ],
			];

			$y_scale = [
				'stacked' => $axes_stacked,
				'title'   => [ 'display' => true, 'text' => $y_label ],
				'grid'    => [ 'display' => $axes_display ],
				'ticks'   => [ 'display' => true ],
			];

			if ($min !== null && $min !== '') {
				$y_scale['min'] = (int) $min;
			}
			if ($max !== null && $max !== '') {
				$y_scale['max'] = (int) $max;
			}

			$opts['scales'] = [
				'x' => $x_scale,
				'y' => $y_scale,
			];
		}

		return $opts;
	}

	/*
	|--------------------------------------------------------------------------
	| Helpers
	|--------------------------------------------------------------------------
	*/

	/**
	 * Get a value from an array with a default fallback.
	 */
	private static function bool($val) {
		return $val === true || $val === 'true';
	}

	/**
	 * Coerce to boolean (accepts true / 'true').
	 */
	private static function parsePadding($padding) {
		if (! is_array($padding)) {
			return [ 'top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0 ];
		}
		return [
			'top'    => (int) preg_replace('/[^0-9]/', '', Arr::get($padding, 'top', '0')),
			'bottom' => (int) preg_replace('/[^0-9]/', '', Arr::get($padding, 'bottom', '0')),
			'left'   => (int) preg_replace('/[^0-9]/', '', Arr::get($padding, 'left', '0')),
			'right' => (int) preg_replace('/[^0-9]/', '', Arr::get($padding, 'right', '0')),
		];
	}
}
