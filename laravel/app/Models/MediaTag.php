<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MediaTag extends Model
{
    protected $table = 'media_tags';
    const UPDATED_AT = null;

    protected $fillable = ['name', 'slug'];
}
