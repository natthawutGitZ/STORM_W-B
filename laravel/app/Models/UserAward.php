<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserAward extends Model
{
    protected $table = 'user_awards';
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'award_id', 'date_awarded',
        'awarded_by', 'notes',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function award()
    {
        return $this->belongsTo(Award::class, 'award_id');
    }

    public function awardedByUser()
    {
        return $this->belongsTo(User::class, 'awarded_by');
    }
}
