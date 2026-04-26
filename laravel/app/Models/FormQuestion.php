<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormQuestion extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'form_questions';

    /**
     * No timestamps on this table.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'form_id',
        'question_text',
        'question_type',
        'options',
        'allow_other',
        'is_required',
        'sort_order',
        'description',
    ];

    /**
     * Get the form that this question belongs to.
     */
    public function form()
    {
        return $this->belongsTo(Form::class, 'form_id');
    }

    /**
     * Get answers for this question.
     */
    public function answers()
    {
        return $this->hasMany(FormAnswer::class, 'question_id');
    }
}
