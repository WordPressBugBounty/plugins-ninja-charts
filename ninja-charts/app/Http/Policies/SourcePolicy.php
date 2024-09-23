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
//    public function index()
//    {
//        return $this->userAccessControl();
//    }
//
//    public function find($sourceId)
//    {
//        return $this->userAccessControl();
//    }
//
//    public function sourceName()
//    {
//        return $this->userAccessControl();
//    }
//
//    public function userAccessControl()
//    {
//        if (is_user_logged_in()) {
//            return current_user_can('manage_options');
//        } else {
//            return false;
//        }
//    }
}
