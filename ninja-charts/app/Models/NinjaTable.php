<?php

namespace NinjaCharts\App\Models;

use NinjaCharts\Framework\Database\Orm\Model;

class NinjaTable extends Model
{
    protected $table = 'posts';

    public function ninjaTableItems()
    {
        return $this->hasMany(NinjaTableItem::class, 'table_id', 'ID');
    }

    public function ninjaTableMetas()
    {
        return $this->hasMany(NinjaTableMeta::class, 'post_id', 'ID');
    }
}
