<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormAnswer extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'form_answers';

    /**
     * No timestamps on this table.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'response_id',
        'question_id',
        'answer_text',
    ];

    /**
     * Get the question that this answer belongs to.
     */
    public function question()
    {
        return $this->belongsTo(FormQuestion::class, 'question_id');
    }

    /**
     * Get the response that this answer belongs to.
     */
    public function response()
    {
        return $this->belongsTo(FormResponse::class, 'response_id');
    }
}
