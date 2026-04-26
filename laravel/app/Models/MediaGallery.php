<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MediaGallery extends Model
{
    protected $table = 'media_gallery';
    const UPDATED_AT = null;
    const CREATED_AT = 'uploaded_at';

    protected $fillable = [
        'filename', 'original_filename', 'title', 'description',
        'category_id', 'album_id', 'file_size', 'mime_type',
        'width', 'height', 'display_order', 'is_video',
        'uploaded_by', 'batch_id', 'video_url',
        'video_platform', 'video_id',
    ];

    public function category()
    {
        return $this->belongsTo(MediaCategory::class, 'category_id');
    }

    public function album()
    {
        return $this->belongsTo(MediaAlbum::class, 'album_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function tags()
    {
        return $this->belongsToMany(MediaTag::class, 'media_gallery_tags', 'media_id', 'tag_id');
    }
}
