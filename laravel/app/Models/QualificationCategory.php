<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QualificationCategory extends Model
{
    protected $table = 'qualification_categories';
    const UPDATED_AT = null;

    protected $fillable = ['name', 'order_index'];

    public function qualifications()
    {
        return $this->hasMany(Qualification::class, 'category_id')->orderBy('order_index');
    }
}
