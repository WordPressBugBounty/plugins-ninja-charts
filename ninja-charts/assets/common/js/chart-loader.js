/*eslint-disable*/

(function () {
    'use strict';

    // Initialize CSS once
    if (!jQuery('style#ninja-loader').length) {
        jQuery('head').append(
            '<style id="ninja-loader">' +
            '.ninja-charts-loader{position:absolute;top:0;left:0;right:0;bottom:0;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,0.9);z-index:999;}' +
            '.ninja-charts-spinner{border:4px solid #f3f3f3;border-top:4px solid #3498db;border-radius:50%;width:40px;height:40px;animation:ninja-spin 1s linear infinite;}' +
            '@keyframes ninja-spin{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}' +
            '</style>'
        );
    }

    // Show loader
    window.NinjaChartsLoader = {
        show: function (element) {
            jQuery(element).css('position', 'relative').prepend('<div class="ninja-charts-loader"><div class="ninja-charts-spinner"></div></div>');
        },
        hide: function (element) {
            jQuery(element).find('.ninja-charts-loader').remove();
        }
    };
})();

