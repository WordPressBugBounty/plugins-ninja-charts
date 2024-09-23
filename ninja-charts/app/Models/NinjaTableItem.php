<?php

namespace NinjaCharts\App\Models;

use NinjaCharts\Framework\Database\Orm\Model;

class NinjaTableItem extends Model
{
    protected $table = 'ninja_table_items';

    public function ninjaTable()
    {
        return $this->belongsTo(NinjaTable::class, 'table_id');
    }

    public function ninjaTableMetas()
    {
        return $this->hasMany(NinjaTableMeta::class, 'table_id', 'id');
    }
}
