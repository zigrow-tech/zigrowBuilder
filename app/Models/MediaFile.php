<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MediaFile extends Model
{
   protected $fillable = [
        'user_id','path','filename','mime','size','width','height',
        'title','alt','description'
    ];
}
