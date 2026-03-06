<?php

namespace NinjaCharts\App\Blocks;

defined('ABSPATH') || exit;

use NinjaCharts\App\Blocks\Engines\EngineRegistry;
use NinjaCharts\App\Blocks\Engines\ChartJsEngine;
use NinjaCharts\App\Blocks\Engines\GoogleChartsEngine;
use NinjaCharts\Framework\Support\Arr;


/**
 * Gutenberg block: Ninja Chart.
 * Dispatches rendering to the appropriate chart engine based on chartLibrary attribute.
 */
class NinjaChartBlock {

	/**
	 * Register the block, engines, and assets.
	 */
	public static function register() {

		// Register chart engines
		EngineRegistry::register(ChartJsEngine::class);
		EngineRegistry::register(GoogleChartsEngine::class);

		self::registerScripts();
		register_block_type(
			NINJA_CHARTS_DIR . 'app/Blocks',
			[ 'render_callback' => [ self::class, 'render' ] ]
		);
	}

	/**
	 * Register editor and frontend script handles so block.json can reference them.
	 */
	private static function registerScripts() {
		$asset_url = NINJA_CHARTS_URL . 'assets/';
		$version   = NINJA_CHARTS_VERSION;

		wp_register_script(
			'ninja-charts-chartjs-editor',
			$asset_url . 'public/js/library/chart.umd.js',
			[],
			'4.4.2',
			true
		);
		wp_register_script(
			'ninja-charts-chartjs-datalabels-editor',
			$asset_url . 'common/js/chartjs-plugin-datalabels.js',
			[ 'ninja-charts-chartjs-editor' ],
			'2.0.0',
			true
		);
		wp_register_script(
			'ninja-charts-chartjs-funnel-editor',
			$asset_url . 'public/js/library/chartjs-chart-funnel.umd.min.js',
			[ 'ninja-charts-chartjs-editor' ],
			'4.2.0',
			true
		);

		wp_register_script(
			'ninja-charts-google-editor',
			$asset_url . 'common/js/google-charts.js',
			[],
			$version,
			true
		);

		wp_register_script(
			'ninja-charts-block-editor',
			$asset_url . 'block/js/ninja-charts-block.js',
			[
				'wp-blocks',
				'wp-element',
				'wp-block-editor',
				'wp-components',
				'wp-i18n',
				'ninja-charts-chartjs-editor',
				'ninja-charts-chartjs-datalabels-editor',
				'ninja-charts-chartjs-funnel-editor',
				'ninja-charts-google-editor',
			],
			$version,
			true
		);

		wp_register_script(
			'ninja-charts-block-frontend',
			$asset_url . 'public/js/ninja-charts-block-render.js',
			[ 'jquery', 'chartjs' ],
			$version,
			true
		);

		wp_register_style(
			'ninja-charts-block-editor-style',
			$asset_url . 'block/css/panel.css',
			[],
			$version
		);
	}

	/**
	 * Server-side render callback.
	 *
	 * @param array $attributes Block attributes.
	 * @return string HTML output.
	 */
	public static function render($attributes) {
		$attributes = wp_parse_args($attributes, [
			'chartLibrary' => 'chart_js',
			'chartType'    => 'bar',
			'labels'       => [ 'Jan', 'Feb', 'Mar' ],
			'datasets'     => [],
			'options'      => [],
		]);

		$chart_library = $attributes['chartLibrary'];
		$chart_type    = $attributes['chartType'];
		$options       = is_array($attributes['options']) ? $attributes['options'] : [];

		// Dispatch to the appropriate engine
		$engine_class = EngineRegistry::get($chart_library);
		if (!$engine_class || !class_exists($engine_class)) {
			return '<div class="ninja-charts-block"><p>' . esc_html__('Chart engine not available.', 'ninja-charts') . '</p></div>';
		}
		$config = $engine_class::buildChartConfig($attributes, $options, $chart_type);

		$asset_url = NINJA_CHARTS_URL . 'assets/';
		$engine_class::enqueueFrontendAssets($asset_url, NINJA_CHARTS_VERSION);

		$id = 'ninja-charts-block-' . wp_unique_id();
		$container_style = self::buildContainerStyle($options);

		return sprintf(
			'<div class="ninja-charts-block" id="%1$s"><div class="ninja-charts-block-container" style="%2$s" data-chart-config="%3$s" data-engine="%4$s"></div></div>',
			esc_attr($id),
			esc_attr($container_style),
			esc_attr(wp_json_encode($config)),
			esc_attr($chart_library)
		);
	}

	/*
	|--------------------------------------------------------------------------
	| Container style (engine-agnostic)
	|--------------------------------------------------------------------------
	*/

	/**
	 * Build the inline CSS for the chart container div.
	 */
	private static function buildContainerStyle($options) {
		$chart      = Arr::get($options, 'chart', []);
		$height     = (int) Arr::get($chart, 'height', 400);
		$width      = (int) Arr::get($chart, 'width', 600);
		$responsive = self::bool(Arr::get($chart, 'responsive', true));
		$bg_color   = Arr::get($chart, 'backgroundColor', '#ffffff');
		$position   = Arr::get($chart, 'position', 'center');
		$radius_raw = Arr::get($chart, 'borderRadius', '0px');

		$parts = [
			'height: ' . $height . 'px',
			'width: ' . ($responsive ? '100%' : $width . 'px'),
			'background-color: ' . $bg_color,
		];

		$parts = array_merge($parts, self::buildBorderCss(Arr::get($chart, 'border', [])));
		$parts[] = 'border-radius: ' . self::buildBorderRadiusCss($radius_raw);

		if (! $responsive && in_array($position, ['left', 'center', 'right'], true)) {
			if ($position === 'center') {
				$parts[] = 'margin-left: auto';
				$parts[] = 'margin-right: auto';
			} elseif ($position === 'right') {
				$parts[] = 'margin-left: auto';
				$parts[] = 'margin-right: 0';
			} else {
				$parts[] = 'margin-left: 0';
				$parts[] = 'margin-right: auto';
			}
		}

		return implode('; ', $parts);
	}

	/**
	 * Build border CSS parts from a BorderBoxControl value.
	 * Supports flat { color, style, width } or split { top, right, bottom, left }.
	 *
	 * @return string[]
	 */
	private static function buildBorderCss($border) {
		if (empty($border)) {
			return [ 'border: 0px solid transparent' ];
		}

		// Split borders (per-side).
		if (isset($border['top']) || isset($border['right']) || isset($border['bottom']) || isset($border['left'])) {
			$parts = [];
			foreach (['top', 'right', 'bottom', 'left'] as $side) {
				$s = isset($border[$side]) && is_array($border[$side]) ? $border[$side] : [];
				$parts[] = sprintf(
					'border-%s: %s %s %s',
					$side,
					Arr::get($s, 'width', '0px'),
					Arr::get($s, 'style', 'solid'),
					Arr::get($s, 'color', 'transparent')
				);
			}
			return $parts;
		}

		// Flat border.
		return [
			sprintf(
				'border: %s %s %s',
				Arr::get($border, 'width', '0px'),
				Arr::get($border, 'style', 'solid'),
				Arr::get($border, 'color', 'transparent')
			),
		];
	}

	/**
	 * Build border-radius CSS value from a BorderRadiusControl value.
	 * Supports string "10px" or object { topLeft, topRight, bottomRight, bottomLeft }.
	 */
	private static function buildBorderRadiusCss($radius) {
		if (is_array($radius)) {
			return sprintf(
				'%s %s %s %s',
				Arr::get($radius, 'topLeft', '0px'),
				Arr::get($radius, 'topRight', '0px'),
				Arr::get($radius, 'bottomRight', '0px'),
				Arr::get($radius, 'bottomLeft', '0px')
			);
		}

		return $radius ?: '0px';
	}

	/*
	|--------------------------------------------------------------------------
	| Helpers
	|--------------------------------------------------------------------------
	*/

	/**
	 * Coerce to boolean (accepts true / 'true').
	 */
	private static function bool($val) {
		return $val === true || $val === 'true';
	}
}
