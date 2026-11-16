<?php

namespace NinjaCharts\App\Blocks;

defined('ABSPATH') || exit;

use NinjaCharts\App\Blocks\Engines\EngineRegistry;
use NinjaCharts\App\Blocks\Engines\ChartJsEngine;
use NinjaCharts\App\Blocks\Engines\GoogleChartsEngine;
use NinjaCharts\Framework\Support\Arr;
use NinjaCharts\App\Constants\ChartConstants;
use NinjaCharts\App\Services\Vite;
use NinjaCharts\App\Helpers\DeviceOptions;


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

		Vite::registerScript(
			'ninja-charts-block-editor',
			'block/js/ninja-charts-block.js',
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
			]
		);

		// Per-device settings (tablet, mobile), read by both block render scripts
		wp_register_script(
			'ninja-charts-device',
			$asset_url . 'public/js/ninja-charts-device.js',
			[],
			$version,
			true
		);

		wp_register_script(
			'ninja-charts-block-frontend',
			$asset_url . 'public/js/ninja-charts-block-render.js',
			[ 'jquery', 'chartjs', 'ninja-charts-device' ],
			$version,
			true
		);

		Vite::registerStyle('ninja-charts-block-editor-style', 'block/css/panel.css');
	}

	/**
	 * Server-side render callback.
	 *
	 * @param array $attributes Block attributes.
	 * @return string HTML output.
	 */
	public static function render($attributes) {
		$attributes = wp_parse_args($attributes, [
			'chartLibrary' => ChartConstants::ENGINE_CHART_JS,
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

		$devices = self::buildDeviceConfigs($engine_class, $attributes, $options, $chart_type);
		if ($devices) {
			$config['devices'] = $devices;
		}

		$asset_url = NINJA_CHARTS_URL . 'assets/';
		$engine_class::enqueueFrontendAssets($asset_url, NINJA_CHARTS_VERSION);

		$id = 'ninja-charts-block-' . wp_unique_id();
		$container_style = self::buildContainerStyle($options);

		// Heights set for tablets and phones, by media query so the box never jumps.
		// $id comes from wp_unique_id() and the heights are intval()'d in heightCss().
		$device_css = DeviceOptions::heightCss('#' . $id . ' .ninja-charts-block-container', $options);
		$device_style = $device_css ? '<style>' . $device_css . '</style>' : '';

		return $device_style . sprintf(
			'<div class="ninja-charts-block" id="%1$s"><div class="ninja-charts-block-container" style="%2$s" data-chart-config="%3$s" data-engine="%4$s"></div></div>',
			esc_attr($id),
			esc_attr($container_style),
			esc_attr(wp_json_encode($config)),
			esc_attr($chart_library)
		);
	}

	/**
	 * The config parts that change on tablets and phones, without the chart data, which the
	 * devices share. Empty when the block has no device settings, so it renders as before.
	 *
	 * @return array [device => config without its data]
	 */
	private static function buildDeviceConfigs($engine_class, $attributes, $options, $chart_type) {
		$devices = [];

		foreach (DeviceOptions::DEVICES as $device) {
			if (! DeviceOptions::overridesFor($options, $device)) {
				continue;
			}
			$device_config = $engine_class::buildChartConfig($attributes, DeviceOptions::resolve($options, $device), $chart_type);
			unset($device_config['data'], $device_config['chartData']);
			$devices[ $device ] = $device_config;
		}

		return $devices;
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
		$bg_color   = self::sanitizeCssColor(Arr::get($chart, 'backgroundColor', '#ffffff'), '#ffffff');
		$position   = Arr::get($chart, 'position', 'center');
		$radius_raw = Arr::get($chart, 'borderRadius', '0px');

		$border = Arr::get($chart, 'border', []);

		$parts = [
			'height: ' . $height . 'px',
			'width: ' . ($responsive ? '100%' : $width . 'px'),
			'max-width: ' . self::buildMaxWidthCss($border),
			'background-color: ' . $bg_color,
		];

		$parts = array_merge($parts, self::buildBorderCss($border));
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
					self::sanitizeCssDimension(Arr::get($s, 'width', '0px'), '0px'),
					self::sanitizeBorderStyle(Arr::get($s, 'style', 'solid')),
					self::sanitizeCssColor(Arr::get($s, 'color', 'transparent'), 'transparent')
				);
			}
			return $parts;
		}

		// Flat border.
		return [
			sprintf(
				'border: %s %s %s',
				self::sanitizeCssDimension(Arr::get($border, 'width', '0px'), '0px'),
				self::sanitizeBorderStyle(Arr::get($border, 'style', 'solid')),
				self::sanitizeCssColor(Arr::get($border, 'color', 'transparent'), 'transparent')
			),
		];
	}

	/**
	 * The max-width that keeps a bordered box inside its container.
	 *
	 * The box is content-box, so its width plus a border on each side would overflow by the
	 * two border widths. Like the shortcode view, cap it at calc(100% - both side borders).
	 * Without a side border this stays '100%', the value every block had before.
	 * resources/block/js/engines/borderMaxWidth.js does the same for the editor preview.
	 *
	 * @param mixed $border Flat { color, style, width } or split { top, right, bottom, left }.
	 * @return string
	 */
	private static function buildMaxWidthCss($border) {
		if (!is_array($border) || empty($border)) {
			return '100%';
		}

		$split = false;
		foreach (['top', 'right', 'bottom', 'left'] as $side) {
			if (isset($border[$side]) && is_array($border[$side])) {
				$split = true;
				break;
			}
		}

		$sides  = $split ? [ Arr::get($border, 'left', []), Arr::get($border, 'right', []) ] : [ $border, $border ];
		$widths = [];

		foreach ($sides as $side) {
			if (!is_array($side) || in_array(Arr::get($side, 'style', 'solid'), ['none', 'hidden'], true)) {
				continue;
			}
			$width = self::sanitizeCssDimension(Arr::get($side, 'width', '0px'), '0px');
			if ((float) $width > 0) {
				$widths[] = $width;
			}
		}

		return $widths ? 'calc(100% - (' . implode(' + ', $widths) . '))' : '100%';
	}

	/**
	 * Build border-radius CSS value from a BorderRadiusControl value.
	 * Supports string "10px" or object { topLeft, topRight, bottomRight, bottomLeft }.
	 */
	private static function buildBorderRadiusCss($radius) {
		if (is_array($radius)) {
			return sprintf(
				'%s %s %s %s',
				self::sanitizeCssDimension(Arr::get($radius, 'topLeft', '0px'), '0px'),
				self::sanitizeCssDimension(Arr::get($radius, 'topRight', '0px'), '0px'),
				self::sanitizeCssDimension(Arr::get($radius, 'bottomRight', '0px'), '0px'),
				self::sanitizeCssDimension(Arr::get($radius, 'bottomLeft', '0px'), '0px')
			);
		}

		return self::sanitizeCssDimension($radius ?: '0px', '0px');
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

	/**
	 * Sanitize a CSS color value. Allows hex colors and the keywords
	 * 'transparent' and 'inherit'. Anything else falls back to $default.
	 */
	private static function sanitizeCssColor($color, $default = '#ffffff') {
		if ($color === 'transparent' || $color === 'inherit') {
			return $color;
		}
		$sanitized = sanitize_hex_color((string) $color);
		return $sanitized ?: $default;
	}

	/**
	 * Sanitize a CSS dimension value (e.g. "10px", "1.5em", "50%").
	 * Rejects anything that could inject additional CSS properties.
	 */
	private static function sanitizeCssDimension($value, $default = '0px') {
		if (preg_match('/^\d+(\.\d+)?(px|em|rem|%|vh|vw)$/', trim((string) $value))) {
			return trim((string) $value);
		}
		return $default;
	}

	/**
	 * Sanitize a CSS border-style keyword against an explicit allowlist.
	 */
	private static function sanitizeBorderStyle($style) {
		$allowed = ['solid', 'dashed', 'dotted', 'double', 'none', 'hidden'];
		return in_array($style, $allowed, true) ? $style : 'solid';
	}
}
