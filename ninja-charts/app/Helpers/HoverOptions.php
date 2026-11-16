<?php

namespace NinjaCharts\App\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

use NinjaCharts\Framework\Support\Arr;

/**
 * What a chart does when the pointer is over a bar, slice or point.
 *
 * Saved as options.hover, every key optional, so a chart without it draws as before:
 *   mode        'highlight' (Chart.js' own stronger shade) | 'none' | 'custom'
 *   color       the hover fill when mode is 'custom'
 *   borderColor the hover outline colour, empty for unchanged
 *   borderWidth the hover outline width, empty for unchanged
 *   enabled     Google Charts only: 'false' hides the hover highlight; with tooltips also
 *               off the chart is static (enableInteractivity false)
 *
 * resources/public/js/ninja-charts-hover.js holds the same rules for the browser, so keep the
 * two in step.
 */
class HoverOptions
{
    const MODES = ['highlight', 'none', 'custom'];

    const MAX_BORDER_WIDTH = 20;

    /** Types whose data draws as points, which take point colours (as POINT_TYPES in the JS) */
    const POINT_TYPES = ['line', 'area', 'radar', 'scatter'];

    const COLOR_PATTERN = '/^(#[0-9a-fA-F]{3,8}|rgba?\(\s*[\d.]+%?\s*,\s*[\d.]+%?\s*,\s*[\d.]+%?\s*(,\s*[\d.]+%?\s*)?\)|transparent)$/';

    /**
     * Keeps only the supported settings, each in its saved form. Returns null when nothing is
     * left, so the caller can drop the key.
     *
     * @param mixed $hover
     * @return array|null
     */
    public static function sanitize($hover)
    {
        if (!is_array($hover)) {
            return null;
        }

        $clean = [];

        $mode = Arr::get($hover, 'mode');
        if (is_string($mode) && in_array($mode, self::MODES, true)) {
            $clean['mode'] = $mode;
        }

        foreach (['color', 'borderColor'] as $key) {
            $color = self::color(Arr::get($hover, $key));
            if ($color !== '') {
                $clean[$key] = $color;
            }
        }

        $width = self::borderWidth(Arr::get($hover, 'borderWidth'));
        if ($width !== null) {
            $clean['borderWidth'] = $width;
        }

        $enabled = Arr::get($hover, 'enabled');
        if ($enabled === true || $enabled === 'true' || $enabled === '1' || $enabled === 1) {
            $clean['enabled'] = 'true';
        } elseif ($enabled === false || $enabled === 'false' || $enabled === '0' || $enabled === 0) {
            $clean['enabled'] = 'false';
        }

        return $clean ?: null;
    }

    /**
     * Sanitizes options.hover in place: invalid settings are dropped, and so is the key when
     * nothing valid is left.
     *
     * @param array $options
     * @return array
     */
    public static function sanitizeOptions($options)
    {
        if (!is_array($options) || !array_key_exists('hover', $options)) {
            return $options;
        }

        $hover = self::sanitize($options['hover']);

        if ($hover === null) {
            unset($options['hover']);
        } else {
            $options['hover'] = $hover;
        }

        return $options;
    }

    /**
     * The hover keys for one Chart.js dataset (hoverBackgroundColor, hoverBorderColor,
     * hoverBorderWidth). Empty when the options have no hover settings.
     *
     * Chart.js reads these for bars, slices, funnel steps and points alike, so one set covers
     * every chart type, per point.
     *
     * @param array  $dataset
     * @param array  $options
     * @param string $chartType The saved chart type, which decides whether "None" uses point colours.
     * @return array
     */
    public static function datasetHover($dataset, $options, $chartType = '')
    {
        return self::datasetHoverFromClean($dataset, self::sanitize(Arr::get((array) $options, 'hover')), $chartType);
    }

    /**
     * datasetHover() for settings already passed through sanitize(), so a chart with many
     * datasets sanitizes them once.
     *
     * @param array      $dataset
     * @param array|null $hover
     * @param string     $chartType
     * @return array
     */
    public static function datasetHoverFromClean($dataset, $hover, $chartType = '')
    {
        if (!$hover || !is_array($dataset)) {
            return [];
        }

        $out  = [];
        $mode = Arr::get($hover, 'mode', 'highlight');

        if ($mode === 'none') {
            // The colours it already has. Points draw with their point colours when set; bars and
            // slices never do (ninja-charts-hover.js POINT_TYPES), and a combo dataset has its own type.
            $points = in_array(Arr::get($dataset, 'type', $chartType), self::POINT_TYPES, true);
            $fill   = $points && array_key_exists('pointBackgroundColor', $dataset) ? $dataset['pointBackgroundColor'] : Arr::get($dataset, 'backgroundColor');
            $stroke = $points && array_key_exists('pointBorderColor', $dataset) ? $dataset['pointBorderColor'] : Arr::get($dataset, 'borderColor');
            $fill   = self::colorOrList($fill);
            $stroke = self::colorOrList($stroke);
            if ($fill !== null) {
                $out['hoverBackgroundColor'] = $fill;
            }
            if ($stroke !== null) {
                $out['hoverBorderColor'] = $stroke;
            }
        } elseif ($mode === 'custom' && isset($hover['color'])) {
            $out['hoverBackgroundColor'] = $hover['color'];
        }

        if (isset($hover['borderColor'])) {
            $out['hoverBorderColor'] = $hover['borderColor'];
        }
        if (isset($hover['borderWidth'])) {
            $out['hoverBorderWidth'] = $hover['borderWidth'];
        }

        return $out;
    }

    /**
     * Google Charts: false only when hover and tooltips are both off. Google has one switch
     * for both, so a chart that shows tooltips stays interactive and hides the highlight
     * instead (googleHideHighlight).
     *
     * @param array $options
     * @return bool
     */
    public static function googleInteractive($options)
    {
        return !(self::googleHoverOff($options) && !self::googleTooltipOn($options));
    }

    /**
     * Google Charts: true when the hover highlight must be hidden while tooltips still show.
     *
     * @param array $options
     * @return bool
     */
    public static function googleHideHighlight($options)
    {
        return self::googleHoverOff($options) && self::googleTooltipOn($options);
    }

    private static function googleHoverOff($options)
    {
        $hover = self::sanitize(Arr::get((array) $options, 'hover'));

        return $hover && Arr::get($hover, 'enabled') === 'false';
    }

    // Tooltips are on unless switched off, as the block's tooltip trigger reads them
    private static function googleTooltipOn($options)
    {
        $enabled = Arr::get((array) $options, 'tooltip.enabled');

        return !in_array($enabled, [false, 'false', 0, '0'], true);
    }

    /**
     * A hex, rgb() or rgba() colour, or '' for anything else.
     *
     * @param mixed $value
     * @return string
     */
    public static function color($value)
    {
        if (!is_string($value)) {
            return '';
        }

        $value = trim($value);

        return preg_match(self::COLOR_PATTERN, $value) ? $value : '';
    }

    /**
     * A whole number from 0 to MAX_BORDER_WIDTH, or null when empty.
     *
     * @param mixed $value
     * @return int|null
     */
    private static function borderWidth($value)
    {
        if (is_array($value) || is_bool($value) || $value === null || $value === '') {
            return null;
        }

        $value = trim((string) $value);
        if (!is_numeric($value)) {
            return null;
        }

        $number = intval($value);
        if ($number < 0) {
            return null;
        }

        return min($number, self::MAX_BORDER_WIDTH);
    }

    /**
     * The dataset's own colour, or list of colours, unchanged: the chart already draws with
     * it. Anything else (an object, a number) is left out.
     *
     * @param mixed $value
     * @return string|array|null
     */
    private static function colorOrList($value)
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_array($value)) {
            // One per slice, in order, so a stray non-colour keeps its place
            return array_values(array_map(function ($item) {
                return is_string($item) ? $item : 'transparent';
            }, $value));
        }

        return null;
    }
}
