<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampaignDoc extends Model
{
    protected $table = 'campaign_docs';
    const UPDATED_AT = null;

    protected $fillable = [
        'campaign_id', 'title', 'content', 'doc_type',
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }
}
