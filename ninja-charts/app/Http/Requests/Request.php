<?php

namespace NinjaCharts\App\Http\Requests;

use NinjaCharts\Framework\Foundation\RequestGuard;

class Request extends RequestGuard
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [];
    }

    public function messages()
    {
        return [];
    }
}
