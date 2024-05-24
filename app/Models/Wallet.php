<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    use HasFactory;
    protected $hidden = [
        'id',
        'user_id',
        'created_at',
        'updated_at'
    ];

    public function setWithdrawableAmountAttribute($value)
    {
        $this->attributes['withdrawable_amount'] = $value < 0 ? 0 : $value;
    }

    public function setAmountAttribute($value)
    {
        $this->attributes['amount'] = $value < 0 ? 0 : $value;
    }

}
