<?php

namespace NinjaCharts\App\Http\Controllers;

use NinjaCharts\Framework\Http\Request\Request;

class WelcomeController extends Controller
{
    public function index(Request $request)
    {
        return [
            'message' => 'Welcome to WPFluent.'
        ];
    }
}
