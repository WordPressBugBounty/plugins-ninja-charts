<?php

namespace NinjaCharts\App\Models;

use NinjaCharts\Framework\Database\Orm\Model as BaseModel;

class Model extends BaseModel
{
    protected $guarded = ['id', 'ID'];
}