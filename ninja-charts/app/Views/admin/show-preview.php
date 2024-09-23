<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" <?php language_attributes(); ?>>
<head>
    <meta http-equiv="Content-type" content="text/html; charset=utf-8"/>
    <meta http-equiv="Imagetoolbar" content="No"/>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>
        <?php esc_attr_e('Preview Chart', 'ninja-charts') ?>
    </title>
    <?php
    wp_head();
    ?>
</head>
<body class="ninja-charts-preview">
<div class="chart_preview">
    <div class="chart_preview_header">
        <div class="chart_preview_header_title">
            <ul>
                <li>
                    [ninja_charts id="<?php echo esc_attr($chartId); ?>"]
                </li>
            </ul>
        </div>
        <div class="chart_preview_header_action">
            <a href="<?php echo esc_url_raw(admin_url('admin.php?page=ninja-charts#/edit-chart/' . $chartId)) ?>">Edit</a>
        </div>
    </div>

    <div class="chart_preview_body">
        <div class="chart_preview_body_wrapper">
            <?php // The shortcode HTML is already escaped line by line at table_inner_html.php  ?>
            <?php echo do_shortcode('[ninja_charts id="' . $chartId . '"]'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped  ?>
        </div>
    </div>
    <div class="chart_preview_fotter">
        <p class="chart_preview_fotter_text">You are seeing preview version of Ninja Charts. This Chats is only accessible for Admin users. Other users
            may not access this page. To use this for in a page please use the following shortcode: [ninja_charts
            id='<?php echo esc_attr($chartId) ?>']</p>
    </div>
</div>
<?php
wp_footer();
?>
</body>
</html>
