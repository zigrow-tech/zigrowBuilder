<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanPayment extends Model
{
   

    protected $fillable = [
        'invoice_id', 
        'user_id',
        'plan_name',
        'price',
        'discount',
        'net_amount_debit',
        'offer_key',
        'offer_type',
        'offer_description',
        'raw_response',
        'txnid',
        'payment_id',
        'mode',
        'bank_ref_num',
        'status',
        'start_date',
        'end_date',
    ];


    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'price' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id'); // ✅ Use custom key
    }
}

