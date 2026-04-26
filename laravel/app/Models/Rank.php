<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rank extends Model
{
    protected $table = 'ranks';
    public $timestamps = false;

    protected $fillable = [
        'name', 'abbreviation', 'nato_code',
        'category', 'image', 'order_index',
    ];
}
