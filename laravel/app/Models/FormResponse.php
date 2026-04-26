<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormResponse extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'form_responses';

    /**
     * Disable updated_at — legacy table uses default timestamp behavior.
     */
    const UPDATED_AT = null;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'form_id',
        'user_id',
        'status',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
    ];

    /**
     * The column used for created_at timestamp.
     */
    const CREATED_AT = 'submitted_at';

    /**
     * Get the form that this response belongs to.
     */
    public function form()
    {
        return $this->belongsTo(Form::class, 'form_id');
    }

    /**
     * Get the answers for this response.
     */
    public function answers()
    {
        return $this->hasMany(FormAnswer::class, 'response_id');
    }
}
