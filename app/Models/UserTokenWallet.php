<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserTokenWallet extends Model
{
    //
    protected $fillable = [
       'user_id',
        'tokens_used_total_all',
        'tokens_used_for_credit',
        'credits_deducted_by_tokens',
    ];
}
