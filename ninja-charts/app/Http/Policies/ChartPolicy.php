<?php

namespace NinjaCharts\App\Http\Policies;

use NinjaCharts\Framework\Foundation\Policy;

class ChartPolicy extends Policy
{
    public function verifyRequest($id = null)
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
//    public function store()
//    {
//        return $this->userAccessControl();
//    }
//
//    public function find($id)
//    {
//        return $this->userAccessControl();
//    }
//
//    public function duplicate()
//    {
//        return $this->userAccessControl();
//    }
//
//    public function processData()
//    {
//        return $this->userAccessControl();
//    }
//
//    public function destroy()
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
