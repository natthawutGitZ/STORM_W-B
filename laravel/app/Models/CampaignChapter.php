<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampaignChapter extends Model
{
    protected $table = 'campaign_chapters';
    const UPDATED_AT = null;

    protected $fillable = [
        'campaign_id', 'title', 'description',
        'event_date', 'sort_order',
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }
}
