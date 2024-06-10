<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    //Transaction reference that starts with "LP" means transfer within LeverPay
    use HasFactory;
    protected $fillable = [
        'user_id',
        'reference_no',
        'tnx_reference_no',
        'amount',
        'transaction_details',
        'balance',
        'status',
        'type',
        'extra'
    ];

    protected $hidden = ['balance'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeCredits($query)
    {
        return $query->where('type', 'credit');
    }

    public function scopeDebits($query)
    {
        return $query->where('type', 'debit');
    }
}
