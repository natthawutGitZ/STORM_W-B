<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPosition extends Model
{
    protected $table = 'user_positions';
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'position_id', 'date_assigned',
        'assigned_by', 'notes',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function position()
    {
        return $this->belongsTo(Position::class, 'position_id');
    }
}
