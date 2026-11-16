<?php

namespace NinjaCharts\App\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

use NinjaCharts\Framework\Support\Arr;

/**
 * Per-device chart settings (tablet, mobile).
 *
 * Saved as options.responsive = ['tablet' => [...], 'mobile' => [...]], each a sparse part of
 * the normal options at the same paths. A missing key inherits, and the cascade runs desktop,
 * then tablet, then mobile. resources/public/js/ninja-charts-device.js holds the same rules for
 * the browser, so keep the two in step.
 */
class DeviceOptions
{
    const MOBILE_MAX = 767;
    const TABLET_MAX = 1024;

    const DEVICES = ['tablet', 'mobile'];

    const NUMBER_PATHS = ['chart.height', 'chart.fontSize', 'title.fontSize'];
    const BOOLEAN_PATHS = ['title.display', 'legend.display'];

    const LEGEND_POSITIONS = ['top', 'bottom', 'left', 'right', 'in', 'labeled', 'chartArea'];

    /** Largest height and font size a device may set, so a typo cannot make a huge chart. */
    const MAX_HEIGHT = 5000;
    const MAX_FONT_SIZE = 200;

    /**
     * Keeps only the supported settings, each in its saved form. Returns null when nothing is
     * left, so the caller can drop the key and the chart stays as it was saved before.
     *
     * Booleans are stored as 'true' / 'false', as the rest of the saved options are.
     *
     * @param mixed $responsive
     * @return array|null
     */
    public static function sanitize($responsive)
    {
        if (!is_array($responsive)) {
            return null;
        }

        $clean = [];

        foreach (self::DEVICES as $device) {
            $settings = Arr::get($responsive, $device);
            if (!is_array($settings)) {
                continue;
            }

            $kept = [];

            foreach (self::NUMBER_PATHS as $path) {
                $value = self::number(Arr::get($settings, $path), $path === 'chart.height' ? self::MAX_HEIGHT : self::MAX_FONT_SIZE);
                if ($value !== null) {
                    Arr::set($kept, $path, $value);
                }
            }

            foreach (self::BOOLEAN_PATHS as $path) {
                $value = self::boolString(Arr::get($settings, $path));
                if ($value !== null) {
                    Arr::set($kept, $path, $value);
                }
            }

            $position = Arr::get($settings, 'legend.position');
            if (is_string($position) && in_array($position, self::LEGEND_POSITIONS, true)) {
                Arr::set($kept, 'legend.position', $position);
            }

            if ($kept) {
                $clean[$device] = $kept;
            }
        }

        return $clean ?: null;
    }

    /**
     * Sanitizes options.responsive in place: invalid settings are dropped, and so is the key
     * when nothing valid is left.
     *
     * @param array $options
     * @return array
     */
    public static function sanitizeOptions($options)
    {
        if (!is_array($options) || !array_key_exists('responsive', $options)) {
            return $options;
        }

        $responsive = self::sanitize($options['responsive']);

        if ($responsive === null) {
            unset($options['responsive']);
        } else {
            $options['responsive'] = $responsive;
        }

        return $options;
    }

    /**
     * The settings a device changes, inherited ones included, as [path => value].
     *
     * @param array  $options
     * @param string $device desktop | tablet | mobile
     * @return array
     */
    public static function overridesFor($options, $device)
    {
        $responsive = self::sanitize(Arr::get((array) $options, 'responsive'));
        $found = [];

        if (!$responsive) {
            return $found;
        }

        $chain = $device === 'mobile' ? ['tablet', 'mobile'] : ($device === 'tablet' ? ['tablet'] : []);

        foreach ($chain as $name) {
            $settings = Arr::get($responsive, $name, []);
            foreach (array_merge(self::NUMBER_PATHS, self::BOOLEAN_PATHS, ['legend.position']) as $path) {
                $value = Arr::get($settings, $path);
                if ($value !== null) {
                    $found[$path] = $value;
                }
            }
        }

        return $found;
    }

    public static function hasOverrides($options)
    {
        return (bool) self::overridesFor($options, 'mobile');
    }

    /**
     * The options as they apply on a device. Unchanged when the device sets nothing.
     *
     * @param mixed  $options
     * @param string $device
     * @return array
     */
    public static function resolve($options, $device)
    {
        $options = is_array($options) ? $options : [];

        foreach (self::overridesFor($options, $device) as $path => $value) {
            Arr::set($options, $path, $value);
        }

        return $options;
    }

    /**
     * Media-query CSS for the heights tablets and phones set, so the box has its final size
     * before any script runs. Empty when no device changes the height.
     *
     * @param string $selector A selector built by the caller from trusted parts only.
     * @param array  $options
     * @return string
     */
    public static function heightCss($selector, $options)
    {
        $rules = [];
        $media = [
            'tablet' => '(min-width: ' . (self::MOBILE_MAX + 1) . 'px) and (max-width: ' . self::TABLET_MAX . 'px)',
            'mobile' => '(max-width: ' . self::MOBILE_MAX . 'px)',
        ];

        foreach ($media as $device => $query) {
            $height = Arr::get(self::overridesFor($options, $device), 'chart.height');
            if ($height !== null) {
                $rules[] = sprintf('@media %s { %s { height: %dpx !important; } }', $query, $selector, intval($height));
            }
        }

        return implode("\n", $rules);
    }

    /**
     * A whole number from 1 to $max, or null to inherit.
     *
     * @return int|null
     */
    private static function number($value, $max)
    {
        if (is_array($value) || is_bool($value) || $value === null || $value === '') {
            return null;
        }

        $value = trim((string) $value);
        if (!is_numeric($value)) {
            return null;
        }

        $number = intval(round((float) $value));
        if ($number < 1) {
            return null;
        }

        return min($number, $max);
    }

    /**
     * @return string|null 'true', 'false', or null to inherit
     */
    private static function boolString($value)
    {
        if ($value === true || $value === 'true' || $value === '1' || $value === 1) {
            return 'true';
        }
        if ($value === false || $value === 'false' || $value === '0' || $value === 0) {
            return 'false';
        }

        return null;
    }
}
