<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserLedger extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'reference_no',
        'debit_amount',
        'credit_amount',
        'transaction_details',
        'balance'
    ];

    public function user()
    {
        return $this->hasMany(User::class,'user_id');
    }
}
