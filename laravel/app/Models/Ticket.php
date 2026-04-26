<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    protected $table = 'tickets';
    const UPDATED_AT = null;

    protected $fillable = [
        'panel_id', 'user_id', 'user_name', 'channel_id',
        'channel_name', 'status', 'closed_by', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'closed_at' => 'datetime',
        ];
    }

    public function panel()
    {
        return $this->belongsTo(TicketPanel::class, 'panel_id');
    }
}
