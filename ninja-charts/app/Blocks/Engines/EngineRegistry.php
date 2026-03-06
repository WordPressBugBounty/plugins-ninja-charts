<?php

namespace NinjaCharts\App\Blocks\Engines;

defined('ABSPATH') || exit;

use NinjaCharts\Framework\Support\Arr;


class EngineRegistry {

	/**
	 * @var array<string, class-string<ChartEngineInterface>>
	 */
	private static $engines = [];

	/**
	 * Register an engine class.
	 */
	public static function register(string $engineClass): void {
		$id = $engineClass::id();
		Arr::set(self::$engines, $id, $engineClass);
	}

	/**
	 * Get an engine class by ID. Falls back to chart_js.
	 */
	public static function get(string $id): string {
		return Arr::get(self::$engines, $id, Arr::get(self::$engines, 'chart_js', ''));
	}

	/**
	 * Check if an engine is registered.
	 */
	public static function has(string $id): bool {
		return Arr::has(self::$engines, $id);
	}
}
