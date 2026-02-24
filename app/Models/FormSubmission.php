<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormSubmission extends Model
{
    //
    // is table me created_at/updated_at nahi hai
     protected $table = 'form_submissions';
    public $timestamps = false; // IMPORTANT

    protected $fillable = [
        'owner_user_id', 
        'template_id',
        'domain',
        'form_key',
        'value',
        'payload',
        'page_url',
        'ip',
        'ua',
        'submitted_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'submitted_at' => 'datetime',
    ];
}
