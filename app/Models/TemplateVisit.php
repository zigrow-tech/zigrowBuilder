<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TemplateVisit extends Model
{
    //

protected $table = 'template_visits';

    protected $fillable = [
        'template_id',
        'domain',
        'visited_at',
    ];

    public $timestamps = false; // since you're using 'visited_at' manually

}
