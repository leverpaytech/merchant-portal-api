<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BillPaymentHistory extends Model
{
    use HasFactory;
    protected $fillable = [
        'id',
        'uuid',
        'user_id',
        'customerId',
        'unit_purchased',
        'price',
        'amount',
        'category',
        'biller',
        'product',
        'item',
        'extra',
        'provider_name',
        'transaction_reference',
        'status',
        'created_at',
        'updated_at'
    ];

    protected $hidden = [
        'id',
        'created_at',
        'updated_at'
    ];

    public static function boot(): void
    {
        parent::boot();
        self::creating(function ($model) {
            $model->uuid = Str::uuid()->toString();
        });
    }
}
