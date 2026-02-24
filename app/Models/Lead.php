<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Lead extends Model
{
    use HasFactory;

    // Define which fields are mass assignable
    protected $fillable = [
        'user_id',
        'package_name',
        'details',
    ];

    // Define the relationship: Lead belongs to a User
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
        
    }
}
