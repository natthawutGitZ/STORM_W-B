<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MediaCategory extends Model
{
    protected $table = 'media_categories';
    const UPDATED_AT = null;

    protected $fillable = ['name', 'slug', 'description'];

    public function albums()
    {
        return $this->hasMany(MediaAlbum::class, 'category_id');
    }

    public function media()
    {
        return $this->hasMany(MediaGallery::class, 'category_id');
    }
}
