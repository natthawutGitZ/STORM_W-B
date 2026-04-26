<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AwardCategory extends Model
{
    protected $table = 'award_categories';
    const UPDATED_AT = null;

    protected $fillable = ['name', 'order_index'];

    public function awards()
    {
        return $this->hasMany(Award::class, 'category_id')->orderBy('order_index');
    }
}
