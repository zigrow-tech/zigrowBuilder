<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HireUsPayment extends Model
{
    //

protected $table = 'hire_us_payments';

    protected $fillable = [
        'invoice_id',  
        'user_id',
        'txnid',
        'mihpayid',
        'status',
        'amount',
        'productinfo',
        'firstname',
        'email',
        'phone',
        'mode',
        'bankcode',
        'bank_ref_num',
        'raw_response',
    ];


public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id'); // ✅ Use custom key
    }
}
