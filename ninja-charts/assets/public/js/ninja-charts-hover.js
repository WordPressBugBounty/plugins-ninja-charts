/**
 * Ninja Charts – what a chart does when the pointer is over a bar, slice or point.
 *
 * One source for the site and the admin: the site loads this file as a classic script
 * (copied as-is to assets/public/js), and the admin and the block editor import it for its
 * side effect and read window.NinjaChartsHover (resources/admin/core/hoverOptions.js).
 * app/Helpers/HoverOptions.php mirrors these rules for the server, so keep the two in step.
 *
 * Saved format, all keys optional (a chart without them draws as it always did):
 *   options.hover.mode        'highlight' (Chart.js' own stronger shade) | 'none' | 'custom'
 *   options.hover.color       the hover fill when mode is 'custom'
 *   options.hover.borderColor the hover outline, empty for unchanged
 *   options.hover.borderWidth the hover outline width, empty for unchanged
 *   options.hover.enabled     Google Charts only: false removes the hover highlight. Tooltips
 *                             follow options.tooltip.enabled; with both off the chart is static.
 */
(function (root) {
	'use strict';

	if (root.NinjaChartsHover) {
		return;
	}

	var MODES = ['highlight', 'none', 'custom'];
	// Types whose data draws as points, which take point colours. A bar or slice never draws
	// its point colours (the server sets a white one on every dataset), so they must not be used.
	var POINT_TYPES = ['line', 'area', 'radar', 'scatter'];
	var MAX_BORDER_WIDTH = 20;
	var COLOR = /^(#[0-9a-f]{3,8}|rgba?\(\s*[\d.]+%?\s*,\s*[\d.]+%?\s*,\s*[\d.]+%?\s*(,\s*[\d.]+%?\s*)?\)|transparent)$/i;

	function settings(options) {
		var hover = options && options.hover;
		return hover && typeof hover === 'object' ? hover : null;
	}

	// A hex, rgb() or rgba() colour, or '' for anything else
	function color(value) {
		if (typeof value !== 'string') return '';
		var trimmed = value.trim();
		return COLOR.test(trimmed) ? trimmed : '';
	}

	// A whole number from 0 to 20, or null when empty
	function borderWidth(value) {
		// Plain numbers only, as the server reads them ("3px" and "1e3" are not)
		if ((typeof value !== 'number' && typeof value !== 'string') || !/^\s*\d+(\.\d+)?\s*$/.test(String(value))) return null;
		var number = parseInt(value, 10);
		if (isNaN(number) || number < 0) return null;
		return Math.min(number, MAX_BORDER_WIDTH);
	}

	function mode(hover) {
		return hover && MODES.indexOf(hover.mode) >= 0 ? hover.mode : 'highlight';
	}

	// The dataset's own colour or list of colours, as the server copies it: a list keeps its
	// order with anything that is not text made transparent; other values are left out
	function copy(value) {
		if (typeof value === 'string') return value;
		if (Array.isArray(value)) {
			return value.map(function (item) { return typeof item === 'string' ? item : 'transparent'; });
		}
		return undefined;
	}

	/**
	 * The hover keys for one Chart.js dataset. Chart.js reads hoverBackgroundColor and the
	 * others for bars, slices, funnel steps and points alike (points look for a point*Hover*
	 * key first, which no renderer sets), so one set covers every chart type, per point.
	 */
	function datasetHover(dataset, hover, chartType) {
		var out = {};
		if (!hover) return out;
		var current = mode(hover);

		if (current === 'none') {
			// The colours it already has. Points draw with their point colours when set; a combo
			// chart's line datasets carry their own type.
			var points = POINT_TYPES.indexOf(dataset.type || chartType) >= 0;
			var fill = points && dataset.pointBackgroundColor !== undefined ? dataset.pointBackgroundColor : dataset.backgroundColor;
			var stroke = points && dataset.pointBorderColor !== undefined ? dataset.pointBorderColor : dataset.borderColor;
			fill = copy(fill);
			stroke = copy(stroke);
			if (fill !== undefined) out.hoverBackgroundColor = fill;
			if (stroke !== undefined) out.hoverBorderColor = stroke;
		} else if (current === 'custom') {
			var custom = color(hover.color);
			if (custom) out.hoverBackgroundColor = custom;
		}

		var outline = color(hover.borderColor);
		if (outline) out.hoverBorderColor = outline;
		var width = borderWidth(hover.borderWidth);
		if (width !== null) out.hoverBorderWidth = width;

		return out;
	}

	/**
	 * The datasets with the saved hover settings applied, as new objects. The same array
	 * when the chart has no hover settings. chartType is the saved type (e.g. 'area', not 'line'),
	 * which decides whether "None" keeps the point colours or the fill colours.
	 */
	function applyChartJs(datasets, options, chartType) {
		var hover = settings(options);
		if (!hover || !Array.isArray(datasets)) return datasets;
		return datasets.map(function (dataset) {
			return Object.assign({}, dataset, datasetHover(dataset || {}, hover, chartType));
		});
	}

	function isOff(value) {
		return value === false || value === 'false' || value === 0 || value === '0';
	}

	// Google Charts: hover switched off, and tooltips on (on unless switched off)
	function googleHoverOff(options) {
		var hover = settings(options);
		return !!hover && isOff(hover.enabled);
	}

	function googleTooltipOn(options) {
		var tooltip = options && options.tooltip;
		return !tooltip || tooltip.enabled === undefined || !isOff(tooltip.enabled);
	}

	/**
	 * Google Charts: false only when hover and tooltips are both off. Google has one switch
	 * for both, so a chart that shows tooltips stays interactive and hides the highlight
	 * with NO_HOVER_CLASS instead (googleHideHighlight).
	 */
	function googleInteractive(options) {
		return !(googleHoverOff(options) && !googleTooltipOn(options));
	}

	/** Google Charts: true when the hover highlight must be hidden while tooltips still show. */
	function googleHideHighlight(options) {
		return googleHoverOff(options) && googleTooltipOn(options);
	}

	// Google draws its hover highlight as unfilled outlines at these stroke opacities
	// (a ring round a slice, boxes round a bar); nothing else in a chart looks like that.
	var NO_HOVER_CLASS = 'ninja-charts-no-hover';
	var NO_HOVER_CSS = ['0.3', '0.15', '0.05'].map(function (opacity) {
		return '.' + NO_HOVER_CLASS + ' svg [fill="none"][stroke-opacity="' + opacity + '"]';
	}).join(',') + '{display:none}';

	/** Adds or removes NO_HOVER_CLASS on a Google chart's box, with its style in that document. */
	function markNoHover(el, hide) {
		if (!el || !el.classList) return;
		el.classList.toggle(NO_HOVER_CLASS, !!hide);
		var doc = el.ownerDocument;
		if (!hide || !doc || doc.getElementById('ninja-charts-no-hover-style')) return;
		var style = doc.createElement('style');
		style.id = 'ninja-charts-no-hover-style';
		style.textContent = NO_HOVER_CSS;
		(doc.head || doc.documentElement).appendChild(style);
	}

	root.NinjaChartsHover = {
		MODES: MODES,
		POINT_TYPES: POINT_TYPES,
		MAX_BORDER_WIDTH: MAX_BORDER_WIDTH,
		color: color,
		borderWidth: borderWidth,
		mode: mode,
		datasetHover: datasetHover,
		applyChartJs: applyChartJs,
		googleInteractive: googleInteractive,
		googleHideHighlight: googleHideHighlight,
		NO_HOVER_CLASS: NO_HOVER_CLASS,
		NO_HOVER_CSS: NO_HOVER_CSS,
		markNoHover: markNoHover,
	};
})(typeof window !== 'undefined' ? window : this);
