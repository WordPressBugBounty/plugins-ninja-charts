<?php

namespace NinjaCharts\App\Http\Policies;

use NinjaCharts\Framework\Request\Request;
use NinjaCharts\Framework\Foundation\Policy;

class UserPolicy extends Policy
{
    /**
     * Check user permission for any method
     * @param  NinjaCharts\Framework\Request\Request $request
     * @return Boolean
     */
    public function verifyRequest(Request $request)
    {
        return current_user_can(ninjaChartsAdminRole());
    }

//    /**
//     * Check user permission for any method
//     * @param  NinjaCharts\Framework\Request\Request $request
//     * @return Boolean
//     */
//    public function create(Request $request)
//    {
//        return current_user_can('manage_options');
//    }
}
