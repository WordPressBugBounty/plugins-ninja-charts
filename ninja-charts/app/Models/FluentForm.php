<?php

namespace NinjaCharts\App\Models;

use NinjaCharts\Framework\Database\Orm\Model;

class FluentForm extends Model
{
    protected $table = 'fluentform_forms';

    public function fluentFormSubmissions()
    {
        return $this->hasMany(FluentFormSubmission::class, 'form_id');
    }
}
