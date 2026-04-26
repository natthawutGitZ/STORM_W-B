<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Qualification extends Model
{
    protected $table = 'qualifications';
    const UPDATED_AT = null;

    protected $fillable = [
        'category_id', 'name', 'description',
        'image', 'order_index',
    ];

    public function category()
    {
        return $this->belongsTo(QualificationCategory::class, 'category_id');
    }
}
