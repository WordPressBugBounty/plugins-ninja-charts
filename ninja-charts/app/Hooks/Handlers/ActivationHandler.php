<?php

namespace NinjaCharts\App\Hooks\Handlers;

use NinjaCharts\Framework\Foundation\Application;
use NinjaCharts\Database\DBMigrator;
use NinjaCharts\Database\DBSeeder;

class ActivationHandler
{
    protected $app = null;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }
    
    public function handle($network_wide = false)
    {
        DBMigrator::run($network_wide);
        DBSeeder::run();
        update_option('_ninja_charts_installed_version', NINJA_CHARTS_VERSION, 'no');
    }
}
