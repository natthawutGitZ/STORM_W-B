<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PositionCategory extends Model
{
    protected $table = 'position_categories';
    const UPDATED_AT = null;

    protected $fillable = ['name', 'order_index'];

    public function positions()
    {
        return $this->hasMany(Position::class, 'category_id')->orderBy('order_index');
    }
}
