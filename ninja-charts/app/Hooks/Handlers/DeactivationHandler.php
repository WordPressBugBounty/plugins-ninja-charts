<?php

namespace NinjaCharts\App\Hooks\Handlers;

use NinjaCharts\Framework\Foundation\Application;

class DeactivationHandler
{
    protected $app = null;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }
    
    public function handle()
    {
        // ...
    }
}
