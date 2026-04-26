<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserQualification extends Model
{
    protected $table = 'user_qualifications';
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'qualification_id', 'date_awarded',
        'awarded_by', 'notes',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function qualification()
    {
        return $this->belongsTo(Qualification::class, 'qualification_id');
    }
}
