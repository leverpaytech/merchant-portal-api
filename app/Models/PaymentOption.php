<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Models\Traits\Uuid;

class PaymentOption extends Model
{
    use HasFactory, Uuid;

    protected $fillable = [
        'currency_id',
        'created_at',
        'updated_at'
    ];

    public static function createPaymentOption($data)
    {
        return self::create($data);
    }

    public function currency()
    {
        return $this->hasMany(Currency::class);
    }
}
