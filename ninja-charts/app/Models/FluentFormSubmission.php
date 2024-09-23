<?php

namespace NinjaCharts\App\Models;

use NinjaCharts\Framework\Database\Orm\Model;

class FluentFormSubmission extends Model
{
    protected $table = 'fluentform_submissions';

    public function fluentForm()
    {
        return $this->belongsTo(FluentForm::class, 'form_id');
    }
}
