<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PublishedDomain extends Model
{
    //
protected $fillable = ['user_id', 'template_id', 'subdomain'];
}
