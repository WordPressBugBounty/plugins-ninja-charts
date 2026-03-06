<?php

namespace NinjaCharts\App\Http\Policies;

use NinjaCharts\Framework\Foundation\Policy;

class SourcePolicy extends Policy
{
    public function verifyRequest($sourceId = null)
    {
        if (is_user_logged_in()) {
            return current_user_can(ninjaChartsAdminRole());
        } else {
            return false;
        }
    }
}
