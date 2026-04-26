<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Award extends Model
{
    protected $table = 'awards';
    const UPDATED_AT = null;

    protected $fillable = [
        'category_id', 'name', 'description',
        'image', 'order_index',
    ];

    public function category()
    {
        return $this->belongsTo(AwardCategory::class, 'category_id');
    }

    public function userAwards()
    {
        return $this->hasMany(UserAward::class, 'award_id');
    }
}
