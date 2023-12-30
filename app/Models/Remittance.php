<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Remittance extends Model
{
    use HasFactory;
    protected $fillable = [
        'id',
        'user_id',
        'voucher_id',
        'amount',
        'currency',
        'account_no',
        'status',
        'payment_date',
        'created_at',
        'updated_at'
    ];
    protected $hidden = ['id', 'user_id','account_no'];

    public function user()
    {
        return $this->belongsTo(User::class,'user_id');
    }

    public function voucher()
    {
        return $this->belongsTo(User::class,'voucher_id');
    }
}
