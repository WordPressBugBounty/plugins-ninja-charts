<?php

namespace NinjaCharts\App\Hooks\Handlers;

use NinjaCharts\App\App;

class PreviewHandler
{
	public function preview()
	{
        $app = App::getInstance();
        $assets = $app['url.assets'];

		if(isset($_GET['ninjatchart_preview'])) {
            wp_enqueue_style('ninja-charts-preview', $assets .'admin/css/preview.css');
            $chartId = intval($_GET['ninjatchart_preview']);
            App::make('view')->render('admin.show-preview', [
                'chartId'      => $chartId,
            ]);
            exit;
        }
	}
}
