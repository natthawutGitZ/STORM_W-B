<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Donation extends Model
{
    protected $table = 'donations';
    const UPDATED_AT = null;

    protected $fillable = [
        'donor_name', 'amount', 'message', 'slip_image',
        'status', 'admin_note', 'ip_address', 'reviewed_at',
        'reviewed_by', 'transaction_ref', 'verify_status', 'verify_data',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'reviewed_at' => 'datetime',
        ];
    }
}
