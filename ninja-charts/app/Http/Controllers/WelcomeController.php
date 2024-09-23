<?php

namespace NinjaCharts\App\Http\Controllers;

use NinjaCharts\Framework\Request\Request;

class WelcomeController extends Controller
{
    public function index(Request $request)
    {
        return [
            'message' => 'Welcome to WPFluent.'
        ];
    }
}
