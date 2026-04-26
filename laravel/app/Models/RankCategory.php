<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RankCategory extends Model
{
    protected $table = 'rank_categories';
    public $timestamps = false;

    protected $fillable = ['name', 'order_index'];

    public function ranks()
    {
        return $this->hasMany(Rank::class, 'category', 'name')->orderBy('order_index');
    }
}
