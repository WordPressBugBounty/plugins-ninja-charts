<?php

namespace NinjaCharts\App\Models;

use NinjaCharts\Framework\Database\Orm\Model;

class NinjaTableMeta extends Model
{
    protected $table = 'postmeta';

    public function ninjaTable()
    {
        return $this->belongsTo(NinjaTable::class, 'post_id');
    }
}
