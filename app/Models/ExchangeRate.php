<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    use HasFactory;
    protected $fillable = [
        'id',
        'rate',
        'local_transaction_rate',
        'international_transaction_rate',
        'funding_rate',
        'conversion_rate',
        'notes'
    ];

    protected $hidden = [
        'id',
        // 'created_at',
        // 'updated_at'
    ];
}
