<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TemplateVisitCount extends Model
{
    //

    protected $fillable = ['template_id','user_id', 'date', 'count'];

    public $timestamps = false;


public function user()
{
    return $this->belongsTo(User::class);
}
}
