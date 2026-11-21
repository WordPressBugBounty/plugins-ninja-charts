<?php

namespace NinjaCharts\App\Modules;

defined('ABSPATH') || exit;

use NinjaCharts\App\Modules\ChartJsCharts\ChartJsModule;
use NinjaCharts\App\Modules\GoogleCharts\GoogleChartModule;
use NinjaCharts\App\Modules\ECharts\EChartsModule;

/**
 * Looks up the rendering engine for a saved chart's render_engine value.
 * The built-in engines register themselves on first use.
 */
class RenderEngineRegistry
{
    /**
     * @var array<string, class-string<RenderEngineInterface>>
     */
    private static $engines = [];

    /**
     * @var bool
     */
    private static $booted = false;

    /**
     * Register an engine class under its id().
     *
     * @param class-string<RenderEngineInterface> $engineClass
     */
    public static function register(string $engineClass): void
    {
        self::boot();
        self::$engines[$engineClass::id()] = $engineClass;
    }

    /**
     * A new engine instance, or null when no engine has this ID.
     *
     * @param mixed $id
     */
    public static function get($id): ?RenderEngineInterface
    {
        self::boot();
        if (!is_string($id) || !isset(self::$engines[$id])) {
            return null;
        }

        $class = self::$engines[$id];

        return new $class();
    }

    /**
     * @param mixed $id
     */
    public static function has($id): bool
    {
        self::boot();

        return is_string($id) && isset(self::$engines[$id]);
    }

    /**
     * @return string[]
     */
    public static function ids(): array
    {
        self::boot();

        return array_keys(self::$engines);
    }

    /**
     * Every chart type any registered engine draws.
     *
     * @return string[]
     */
    public static function chartTypes(): array
    {
        self::boot();
        $types = [];
        foreach (self::$engines as $class) {
            $types = array_merge($types, $class::chartTypes());
        }

        return array_values(array_unique($types));
    }

    private static function boot(): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        foreach ([ChartJsModule::class, GoogleChartModule::class, EChartsModule::class] as $class) {
            self::$engines[$class::id()] = $class;
        }
    }
}
