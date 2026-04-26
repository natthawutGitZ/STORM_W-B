<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Position extends Model
{
    protected $table = 'positions';
    const UPDATED_AT = null;

    protected $fillable = [
        'category_id', 'name', 'description',
        'image', 'order_index',
    ];

    public function category()
    {
        return $this->belongsTo(PositionCategory::class, 'category_id');
    }
}
