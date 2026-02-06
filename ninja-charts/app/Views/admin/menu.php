<div id="<?php echo esc_attr($slug); ?>-app" class="warp fconnector_app">
    <div class="fframe_app">
        <div class="fframe_main-menu-items">

            <div class="plugin_name">
                <?php echo wp_get_attachment_image( $logo, 'full', false, [
                    'style' => 'height: 35px;',
                    'alt'   => 'image',
                ] ); ?>

            </div>

            <div class="fframe_handheld">
                <span class="dashicons dashicons-menu-alt3"></span>
            </div>

            <ul class="fframe_menu">
                <?php foreach ($menuItems as $ninja_charts_item): ?>
                    <?php $ninja_charts_hasSubMenu = !empty($ninja_charts_item['sub_items']); ?>
                    <li data-key="<?php echo esc_attr($ninja_charts_item['key']); ?>"
                        class="fframe_menu_item <?php echo ($ninja_charts_hasSubMenu) ? 'fframe_has_sub_items' : ''; ?> fframe_item_<?php echo esc_attr($ninja_charts_item['key']); ?>">
                        <a class="fframe_menu_primary" href="<?php echo esc_url($ninja_charts_item['permalink']); ?>">
                            <?php echo esc_attr($ninja_charts_item['label']); ?>
                            <?php if ($ninja_charts_hasSubMenu) { ?>
                                <span class="dashicons dashicons-arrow-down-alt2"></span>
                            <?php } ?></a>
                        <?php if ($ninja_charts_hasSubMenu): ?>
                            <div class="fframe_submenu_items">
                                <?php foreach ($ninja_charts_item['sub_items'] as $ninja_charts_sub_item): ?>
                                    <a href="<?php echo esc_url($ninja_charts_sub_item['permalink']); ?>"><?php echo esc_attr($ninja_charts_sub_item['label']); ?></a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="fframe_body">
            <div class="fs_route_wrapper">
                <router-view></router-view>
            </div>
        </div>
    </div>
</div>
