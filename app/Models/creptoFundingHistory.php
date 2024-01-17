<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class creptoFundingHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'uuid',
        'user_id',
        'transaction_hash',
        'amount',
        'status',
        'created_at',
        'updated_at'
    ];
}
