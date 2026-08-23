<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingInquiry extends Model
{
    use HasFactory;

    protected $fillable = [
        'type','name','phone','email','service_details','notes','status','source',
        'quote_amount','quote_currency','quote_details','quoted_at'
    ];

    protected $casts = [
        'service_details' => 'array',
        'quote_details' => 'array',
        'quote_amount' => 'float',
        'quoted_at' => 'datetime',
    ];
}
