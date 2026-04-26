<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketPanel extends Model
{
    protected $table = 'ticket_panels';

    protected $fillable = [
        'title', 'description', 'channel_id', 'category_id',
        'support_role_id', 'button_text', 'button_color',
        'button_emoji', 'embed_color', 'welcome_message',
        'max_tickets', 'message_id', 'is_active',
    ];

    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'panel_id');
    }
}
