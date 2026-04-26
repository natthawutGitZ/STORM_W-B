<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MediaAlbum extends Model
{
    protected $table = 'media_albums';
    const UPDATED_AT = null;

    protected $fillable = [
        'title', 'description', 'category_id',
        'cover_image_id', 'status', 'created_by',
    ];

    public function category()
    {
        return $this->belongsTo(MediaCategory::class, 'category_id');
    }

    public function media()
    {
        return $this->hasMany(MediaGallery::class, 'album_id')->orderBy('display_order');
    }

    public function coverImage()
    {
        return $this->belongsTo(MediaGallery::class, 'cover_image_id');
    }

    public function tags()
    {
        return $this->belongsToMany(MediaTag::class, 'media_album_tags', 'album_id', 'tag_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
