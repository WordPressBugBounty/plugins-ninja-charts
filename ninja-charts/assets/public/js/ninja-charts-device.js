/**
 * Ninja Charts – per-device settings (tablet, mobile).
 *
 * One source for the site and the admin: the site loads this file as a classic script
 * (copied as-is to assets/public/js), and the admin imports it for its side effect and
 * reads window.NinjaChartsDevice (resources/admin/core/deviceOptions.js).
 *
 * Saved format: options.responsive = { tablet: {...}, mobile: {...} }. Each is a sparse
 * part of the normal options, at the same paths. A missing key means "inherit", and the
 * cascade runs desktop, then tablet, then mobile. app/Helpers/DeviceOptions.php mirrors
 * these rules for the server, so keep the two in step.
 */
(function (root) {
	'use strict';

	if (root.NinjaChartsDevice) {
		return;
	}

	var DEVICES = ['desktop', 'tablet', 'mobile'];

	// The only settings a device can change
	var PATHS = ['chart.height', 'chart.fontSize', 'title.display', 'title.fontSize', 'legend.display', 'legend.position'];

	// Viewport widths: mobile up to 767px, tablet 768px to 1024px, desktop above
	var MOBILE_MAX = 767;
	var TABLET_MAX = 1024;

	var MEDIA = {
		mobile: '(max-width: ' + MOBILE_MAX + 'px)',
		tablet: '(min-width: ' + (MOBILE_MAX + 1) + 'px) and (max-width: ' + TABLET_MAX + 'px)',
	};

	function deviceForWidth(width) {
		if (width <= MOBILE_MAX) return 'mobile';
		if (width <= TABLET_MAX) return 'tablet';
		return 'desktop';
	}

	// The device of the current viewport, measured the way the height CSS measures it
	function currentDevice() {
		if (root.matchMedia) {
			if (root.matchMedia(MEDIA.mobile).matches) return 'mobile';
			if (root.matchMedia(MEDIA.tablet).matches) return 'tablet';
			return 'desktop';
		}
		return deviceForWidth(root.innerWidth || 0);
	}

	function isSet(value) {
		return value !== undefined && value !== null && value !== '';
	}

	function read(source, path) {
		var keys = path.split('.');
		var value = source;
		for (var i = 0; i < keys.length; i++) {
			if (value === undefined || value === null || typeof value !== 'object') return undefined;
			value = value[keys[i]];
		}
		return value;
	}

	function write(target, path, value) {
		var keys = path.split('.');
		var last = keys.pop();
		var holder = target;
		keys.forEach(function (key) {
			if (!holder[key] || typeof holder[key] !== 'object') holder[key] = {};
			holder = holder[key];
		});
		holder[last] = value;
	}

	// The devices whose settings apply on this one, widest first
	function chainFor(device) {
		if (device === 'mobile') return ['tablet', 'mobile'];
		if (device === 'tablet') return ['tablet'];
		return [];
	}

	// { path: value } for every setting the device changes, inherited ones included
	function overridesFor(options, device) {
		var responsive = options && options.responsive;
		var found = {};
		if (!responsive || typeof responsive !== 'object') return found;
		chainFor(device).forEach(function (name) {
			var settings = responsive[name];
			if (!settings || typeof settings !== 'object') return;
			PATHS.forEach(function (path) {
				var value = read(settings, path);
				if (isSet(value)) found[path] = value;
			});
		});
		return found;
	}

	function hasOverrides(options) {
		return Object.keys(overridesFor(options, 'mobile')).length > 0;
	}

	/**
	 * The options as they apply on a device. Returns the same object when the device
	 * changes nothing, so a chart without device settings draws exactly as before.
	 */
	function resolveDeviceOptions(options, device) {
		var overrides = overridesFor(options, device);
		var paths = Object.keys(overrides);
		if (!paths.length) return options;
		var resolved = JSON.parse(JSON.stringify(options));
		paths.forEach(function (path) {
			write(resolved, path, overrides[path]);
		});
		return resolved;
	}

	// The value a device shows when it sets nothing itself: the next wider device's
	function inheritedValue(options, device, path) {
		var wider = device === 'mobile' ? 'tablet' : 'desktop';
		return read(resolveDeviceOptions(options, wider), path);
	}

	/**
	 * Calls back with the new device whenever the viewport crosses a breakpoint,
	 * debounced so a drag across one redraws once.
	 */
	function onDeviceChange(callback, delay) {
		var device = currentDevice();
		var timer = null;
		root.addEventListener('resize', function () {
			clearTimeout(timer);
			timer = setTimeout(function () {
				var next = currentDevice();
				if (next === device) return;
				device = next;
				callback(next);
			}, delay === undefined ? 150 : delay);
		});
		return device;
	}

	root.NinjaChartsDevice = {
		DEVICES: DEVICES,
		PATHS: PATHS,
		MEDIA: MEDIA,
		deviceForWidth: deviceForWidth,
		currentDevice: currentDevice,
		overridesFor: overridesFor,
		hasOverrides: hasOverrides,
		resolveDeviceOptions: resolveDeviceOptions,
		inheritedValue: inheritedValue,
		onDeviceChange: onDeviceChange,
	};
})(typeof window !== 'undefined' ? window : globalThis);
