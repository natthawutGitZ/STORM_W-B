<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    protected $table = 'campaigns';

    protected $fillable = [
        'title', 'image_path', 'description',
        'status', 'created_by',
    ];

    public function chapters()
    {
        return $this->hasMany(CampaignChapter::class, 'campaign_id')->orderBy('sort_order');
    }

    public function docs()
    {
        return $this->hasMany(CampaignDoc::class, 'campaign_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
