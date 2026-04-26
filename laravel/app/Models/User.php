<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'users';

    /**
     * Set to null to disable updated_at, as the legacy table only has created_at
     */
    const UPDATED_AT = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'steamid',
        'username',
        'email',
        'google_id',
        'password',
        'generated_password',
        'personaname',
        'avatar',
        'profileurl',
        'role',
        'rank',
        'position',
        'status',
        'join_date',
        'resume_data',
        'parent_id',
        'coc_sort_order',
        'coc_x',
        'coc_y',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'generated_password',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'join_date' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the form responses submitted by this user.
     */
    public function formResponses()
    {
        return $this->hasMany(FormResponse::class, 'user_id', 'id');
    }
}
