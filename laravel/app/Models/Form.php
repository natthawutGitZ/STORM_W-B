<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Form extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'forms';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'title',
        'description',
        'banner_image',
        'created_by',
        'discord_webhook_url',
        'is_active',
        'is_main_application',
        'banner_position_y',
        'webhook_url_staff',
        'webhook_url_welcome',
        'webhook_url_public',
        'is_main_form',
        'give_role_id',
    ];

    /**
     * Get the responses for this form.
     */
    public function responses()
    {
        return $this->hasMany(FormResponse::class, 'form_id');
    }

    /**
     * Get the questions for this form.
     */
    public function questions()
    {
        return $this->hasMany(FormQuestion::class, 'form_id')->orderBy('sort_order');
    }
}
