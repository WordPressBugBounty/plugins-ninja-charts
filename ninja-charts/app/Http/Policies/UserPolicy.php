<?php

namespace NinjaCharts\App\Http\Policies;

use NinjaCharts\Framework\Http\Request\Request;
use NinjaCharts\Framework\Foundation\Policy;

class UserPolicy extends Policy
{
    /**
     * Check user permission for any method
     * @param  NinjaCharts\Framework\Http\Request\Request $request
     * @return Boolean
     */
    public function verifyRequest(Request $request)
    {
        return current_user_can(ninjaChartsAdminRole());
    }

}
