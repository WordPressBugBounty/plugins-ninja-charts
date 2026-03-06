<?php

namespace NinjaCharts\App\Blocks\Engines;

defined('ABSPATH') || exit;

use NinjaCharts\Framework\Support\Arr;


/**
 * Google Charts engine – builds Google Charts config and enqueues Google Charts scripts.
 */
class GoogleChartsEngine implements ChartEngineInterface {

	/**
	 * Internal type → Google Charts class name.
	 */
	private static $type_map = [
		'bar'           => 'ColumnChart',
		'horizontalBar' => 'BarChart',
		'line'          => 'LineChart',
		'area'          => 'AreaChart',
		'pie'           => 'PieChart',
		'doughnut'      => 'PieChart',
		'scatter'       => 'ScatterChart',
		'bubble'        => 'BubbleChart',
	];

	/**
	 * {@inheritDoc}
	 */
	public static function id(): string {
		return 'google_charts';
	}

	/**
	 * {@inheritDoc}
	 */
	public static function renderElement(): string {
		return 'div';
	}

	/**
	 * {@inheritDoc}
	 */
	public static function buildChartConfig(array $attributes, array $options, string $chartType): array {
		$google_type = Arr::get(self::$type_map, $chartType, 'ColumnChart');

		$chart_data = self::buildDataTable($attributes, $chartType, $options);
		$chart_opts = self::buildOptions($options, $chartType, $attributes);

		return [
			'chartType'    => $google_type,
			'chartData'    => $chart_data,
			'chartOptions' => $chart_opts,
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public static function enqueueFrontendAssets(string $assetUrl, string $version): void {
		wp_enqueue_script(
			'google-charts-loader',
			$assetUrl . 'common/js/google-charts.js',
			[],
			$version,
			true
		);
		wp_enqueue_script(
			'ninja-charts-google-block-frontend',
			$assetUrl . 'public/js/ninja-charts-google-block-render.js',
			[ 'google-charts-loader' ],
			$version,
			true
		);
	}

	/*
	|--------------------------------------------------------------------------
	| Data table builder
	|--------------------------------------------------------------------------
	*/

	/**
	 * Convert labels/datasets to a 2D array for arrayToDataTable().
	 */
	private static function buildDataTable(array $attributes, string $chartType, array $options = []): array {
		$labels   = Arr::get($attributes, 'labels', []);
		$datasets = Arr::get($attributes, 'datasets', []);

		if (empty($datasets)) {
			return [['Label', 'Value']];
		}

		// Bubble charts
		if ($chartType === 'bubble') {
			$axes = Arr::get($options, 'axes', []);
			$x_label = trim(Arr::get($axes, 'x_axis_label', '')) ?: 'X';
			$y_label = trim(Arr::get($axes, 'y_axis_label', '')) ?: 'Y';
			$r_label = trim(Arr::get($axes, 'bubble_r_label', '')) ?: 'Size';
			$header = ['ID', $x_label, $y_label, $r_label];
			$rows   = [];
			foreach ($datasets as $ds) {
				$data = Arr::get($ds, 'data', []);
				$ds_label = Arr::get($ds, 'label', 'Dataset');
				foreach ($data as $i => $pt) {
					$point_label = !empty($pt['label']) ? $pt['label'] : ($ds_label . ' ' . ($i + 1));
					$rows[] = [
						$point_label,
						Arr::get($pt, 'x', 0),
						Arr::get($pt, 'y', 0),
						Arr::get($pt, 'r', 10),
					];
				}
			}
			return array_merge([$header], $rows);
		}

		// Scatter charts
		if ($chartType === 'scatter') {
			$axes = Arr::get($options, 'axes', []);
			$x_label = trim(Arr::get($axes, 'x_axis_label', '')) ?: 'X';
			$y_label = trim(Arr::get($axes, 'y_axis_label', '')) ?: 'Y';
			// Header with tooltip role columns per dataset
			$header = [$x_label];
			foreach ($datasets as $ds) {
				$header[] = Arr::get($ds, 'label', 'Dataset');
				$header[] = ['type' => 'string', 'role' => 'tooltip'];
			}
			// Gather unique x values
			$x_values = [];
			foreach ($datasets as $ds) {
				$data = Arr::get($ds, 'data', []);
				foreach ($data as $pt) {
					$x = Arr::get($pt, 'x', 0);
					$x_values[$x] = true;
				}
			}
			ksort($x_values);
			$rows = [];
			foreach (array_keys($x_values) as $x) {
				$row = [$x];
				foreach ($datasets as $ds) {
					$data  = Arr::get($ds, 'data', []);
					$found_pt = null;
					foreach ($data as $pt) {
						if (Arr::get($pt, 'x', 0) === $x) {
							$found_pt = $pt;
							break;
						}
					}
					if ($found_pt !== null) {
						$y = Arr::get($found_pt, 'y', 0);
						$point_name = Arr::get($found_pt, 'label', '');
						$tip = $point_name
							? $point_name . "\n" . $x_label . ': ' . $x . ', ' . $y_label . ': ' . $y
							: $x_label . ': ' . $x . ', ' . $y_label . ': ' . $y;
						$row[] = $y;
						$row[] = $tip;
					} else {
						$row[] = null;
						$row[] = null;
					}
				}
				$rows[] = $row;
			}
			return array_merge([$header], $rows);
		}

		// Circular charts (pie, doughnut)
		if (in_array($chartType, ['pie', 'doughnut'], true)) {
			$header = ['Label', 'Value'];
			$ds     = $datasets[0];
			$data   = Arr::get($ds, 'data', []);
			$rows   = [];
			foreach ($labels as $i => $lbl) {
				$rows[] = [$lbl, Arr::get($data, $i, 0)];
			}
			if (empty($rows)) {
				$rows[] = ['', 0];
			}
			return array_merge([$header], $rows);
		}

		// Standard charts (bar, horizontalBar, line, area)
		$header = ['Label'];
		foreach ($datasets as $ds) {
			$header[] = Arr::get($ds, 'label', 'Dataset');
		}
		$rows = [];
		foreach ($labels as $i => $lbl) {
			$row = [$lbl];
			foreach ($datasets as $ds) {
				$data  = Arr::get($ds, 'data', []);
				$row[] = Arr::get($data, $i, 0);
			}
			$rows[] = $row;
		}
		if (empty($rows)) {
			$rows[] = array_merge([''], array_fill(0, count($datasets), 0));
		}
		return array_merge([$header], $rows);
	}

	/*
	|--------------------------------------------------------------------------
	| Options builder
	|--------------------------------------------------------------------------
	*/

	/**
	 * Map stored block options to Google Charts options.
	 */
	private static function buildOptions(array $options, string $chartType, array $attributes): array {
		$chart   = Arr::get($options, 'chart', []);
		$title   = Arr::get($options, 'title');
		$legend  = Arr::get($options, 'legend', []);
		$tooltip = Arr::get($options, 'tooltip', []);
		$axes    = Arr::get($options, 'axes', []);
		$anim    = Arr::get($options, 'googleAnimation', []);
		$opts = [
			'backgroundColor' => Arr::get($chart, 'backgroundColor', '#ffffff'),
			'fontSize'        => (int) Arr::get($chart, 'fontSize', 12),
		];

		// Title — display defaults to true (opt-out)
		$title_display = Arr::get($title, 'display', true);
		$title_visible = $title_display !== false && $title_display !== 'false';
		if ($title_visible && Arr::get($title, 'text', '')) {
			$opts['title'] = Arr::get($title, 'text', '');
			// Title position: 'in' or 'out' (non-circular charts only)
			if (!in_array($chartType, ['pie', 'doughnut'], true)) {
				$pos = Arr::get($title, 'position', 'out');
				$opts['titlePosition'] = in_array($pos, ['in', 'out'], true) ? $pos : 'out';
			}
		}
		// Always set titleTextStyle so color/fontSize are applied
		$font_style = Arr::get($title, 'fontStyle', 'normal');
		$opts['titleTextStyle'] = [
			'color'    => Arr::get($title, 'fontColor', '#000000'),
			'fontSize' => (int) Arr::get($title, 'fontSize', 16),
			'italic'   => $font_style === 'italic' || $font_style === 'oblique',
			'bold'     => false,
		];

		// Legend
		if (!self::bool(Arr::get($legend, 'display', true))) {
			$opts['legend'] = ['position' => 'none'];
		} else {
			$opts['legend'] = [
				'position'  => Arr::get($legend, 'position', 'top'),
				'alignment' => Arr::get($legend, 'alignment', 'center'),
				'textStyle' => [
					'color'    => Arr::get($legend, 'fontColor', '#000000'),
					'fontSize' => (int) Arr::get($legend, 'fontSize', 12),
				],
			];
		}

		// Tooltip
		$trigger = self::bool(Arr::get($tooltip, 'enabled', true))
			? Arr::get($tooltip, 'trigger', 'focus')
			: 'none';
		$opts['tooltip'] = [
			'trigger'   => $trigger,
			'textStyle' => [
				'color'    => Arr::get($tooltip, 'titleFontColor', '#000000'),
				'fontSize' => (int) Arr::get($tooltip, 'titleFontSize', 12),
			],
		];

		// Axes (not for circular charts)
		$no_axes = in_array($chartType, ['pie', 'doughnut'], true);
		if (!$no_axes) {
			$opts['hAxis'] = [
				'title'     => Arr::get($axes, 'x_axis_label', ''),
				'textStyle' => ['color' => Arr::get($chart, 'fontColor', '#000000')],
			];
			$opts['vAxis'] = [
				'title'     => Arr::get($axes, 'y_axis_label', ''),
				'textStyle' => ['color' => Arr::get($chart, 'fontColor', '#000000')],
				'minValue'  => 0,
			];
			$min = Arr::get($axes, 'vertical_min_tick');
			$max = Arr::get($axes, 'vertical_max_tick');
			if ($min !== null && $min !== '') {
				$opts['vAxis']['minValue'] = (float) $min;
			}
			if ($max !== null && $max !== '') {
				$opts['vAxis']['maxValue'] = (float) $max;
			}
			if (self::bool(Arr::get($axes, 'stacked', false))) {
				$opts['isStacked'] = true;
			}
		}

		// Doughnut
		if ($chartType === 'doughnut') {
			$opts['pieHole'] = 0.4;
		}

		// 3D
		if (self::bool(Arr::get($chart, 'threeD', false)) && in_array($chartType, ['pie', 'doughnut'], true)) {
			$opts['is3D'] = true;
		}

		// Colors
		$datasets = Arr::get($attributes, 'datasets', []);
		if (in_array($chartType, ['pie', 'doughnut'], true)) {
			if (!empty($datasets[0]) && is_array(Arr::get($datasets[0], 'backgroundColor', null))) {
				$slices = [];
				foreach ($datasets[0]['backgroundColor'] as $i => $color) {
					$slices[$i] = ['color' => $color];
				}
				$opts['slices'] = $slices;
			}
		} else {
			$colors = [];
			foreach ($datasets as $ds) {
				$colors[] = Arr::get($ds, 'backgroundColor', null) && is_string(Arr::get($ds, 'backgroundColor', null))
						? $ds['backgroundColor']
						: '#3498db';
			}
			if (!empty($colors)) {
				$opts['colors'] = $colors;
			}
		}

		// Chart area – constrain plot area (padding is handled via CSS on container)
		if (!$no_axes) {
			$opts['chartArea'] = [
				'width'  => '80%',
				'height' => '70%',
			];
		}

		// Animation
		$opts['animation'] = [
			'duration' => (int) Arr::get($anim, 'duration', 1000),
			'easing'   => Arr::get($anim, 'easing', 'out'),
			'startup'  => self::bool(Arr::get($anim, 'startup', true)),
		];

		return $opts;
	}

	/*
	|--------------------------------------------------------------------------
	| Helpers
	|--------------------------------------------------------------------------
	*/

	private static function bool($val) {
		return $val === true || $val === 'true';
	}
}
