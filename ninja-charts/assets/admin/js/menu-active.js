(function () {
    jQuery(document).ready(function () {
        jQuery('#toplevel_page_ninja_tables')
            .first()
            .removeClass('wp-not-current-submenu')
            .addClass('wp-has-current-submenu current');
    });
})();
