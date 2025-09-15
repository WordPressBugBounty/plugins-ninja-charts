<?php

namespace NinjaCharts\App\Hooks\Handlers;

use NinjaCharts\App\App;

class PreviewHandler
{
    public function preview()
    {
        $app    = App::getInstance();
        $assets = $app['url.assets'];

        if (isset($_GET['ninjatchart_preview']) && ninjaChartsAdminRole()) { // phpcs:ignore
            wp_enqueue_style('ninja-charts-preview', $assets . 'admin/css/preview.css', [], NINJA_CHARTS_VERSION);
            $chartId = intval($_GET['ninjatchart_preview']); // phpcs:ignore
            App::make('view')->render('admin.show-preview', [
                'chartId' => $chartId,
            ]);
            exit;
        }
    }
}
