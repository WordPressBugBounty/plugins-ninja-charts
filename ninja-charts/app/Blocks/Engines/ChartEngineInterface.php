<?php

namespace NinjaCharts\App\Blocks\Engines;

defined('ABSPATH') || exit;

interface ChartEngineInterface {

	/**
	 * Engine identifier (matches chartLibrary attribute).
	 */
	public static function id(): string;

	/**
	 * Build the chart config array for frontend rendering.
	 */
	public static function buildChartConfig(array $attributes, array $options, string $chartType): array;

	/**
	 * Enqueue frontend scripts/styles needed to render charts.
	 */
	public static function enqueueFrontendAssets(string $assetUrl, string $version): void;

	/**
	 * The DOM element type the engine renders into ('canvas' or 'div').
	 */
	public static function renderElement(): string;
}
