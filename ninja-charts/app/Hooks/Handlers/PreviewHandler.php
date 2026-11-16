<?php

namespace NinjaCharts\App\Hooks\Handlers;

use NinjaCharts\App\App;
use NinjaCharts\App\Services\Vite;

class PreviewHandler
{
    public function preview()
    {
        if (isset($_GET['ninjatchart_preview']) && current_user_can(ninjaChartsAdminRole())) { // phpcs:ignore
            Vite::enqueueStyle('ninja-charts-preview', 'admin/css/preview.css');
            $chartId = intval($_GET['ninjatchart_preview']); // phpcs:ignore
            App::make('view')->render('admin.show-preview', [
                'chartId' => $chartId,
            ]);
            exit;
        }
    }
}
