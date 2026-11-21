<?php

namespace NinjaCharts\App\Blocks\Engines;

defined('ABSPATH') || exit;

use NinjaCharts\Framework\Support\Arr;
use NinjaCharts\App\Constants\ChartConstants;
use NinjaCharts\App\Modules\ECharts\EChartsModule;


/**
 * ECharts engine for the block: new blocks use it, as new shortcode charts do.
 *
 * The block's attributes go to the page as they are: resources/public/echarts/echarts-render.js
 * turns them into an ECharts option with the same buildOption() the shortcode charts use
 * (blockData.js maps the block's chart-wide line settings), so there is one mapping to keep.
 */
class EChartsEngine implements ChartEngineInterface {

	/**
	 * {@inheritDoc}
	 */
	public static function id(): string {
		return ChartConstants::ENGINE_ECHARTS;
	}

	/**
	 * {@inheritDoc}
	 */
	public static function renderElement(): string {
		return 'div';
	}

	/**
	 * {@inheritDoc}
	 *
	 * NinjaChartBlock::render() drops 'data' from the device configs, so a tablet or phone
	 * config is only its options.
	 */
	public static function buildChartConfig(array $attributes, array $options, string $chartType): array {
		$labels   = Arr::get($attributes, 'labels', []);
		$datasets = Arr::get($attributes, 'datasets', []);

		return [
			'type'    => $chartType,
			'data'    => [
				'labels'   => is_array($labels) ? array_values($labels) : [],
				'datasets' => is_array($datasets) ? array_values($datasets) : [],
			],
			'options' => $options,
		];
	}

	/**
	 * {@inheritDoc}
	 *
	 * The shortcode's ECharts bundle draws blocks too. Its loader and hover scripts are
	 * registered by the shortcode, which may not be on the page, so they are registered here
	 * first (registering a handle twice keeps the first).
	 */
	public static function enqueueFrontendAssets(string $assetUrl, string $version): void {
		// Once a page: each ECharts block (or a shortcode) may have loaded it already
		if (wp_script_is('ninja-charts-echarts-render', 'enqueued')) {
			return;
		}

		wp_register_script('ninja_charts_loader', $assetUrl . 'common/js/chart-loader.js', [ 'jquery' ], $version, true);
		wp_register_script('ninja-charts-hover', $assetUrl . 'public/js/ninja-charts-hover.js', [], $version, true);

		( new EChartsModule() )->enqueueShortcodeAssets();
	}
}
