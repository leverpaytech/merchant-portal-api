<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    // type = 0: non leverpay email, 1: leverpay email
    // status = 0: pending, 1: paid, 2: cancelled
    use HasFactory;
    protected $fillable = [
        'id',
        'uuid',
        'user_id',
        'email',
        'currency',
        'type',
        'merchant_id',
        'product_name',
        'price',
        'vat',
        'fee',
        'url',
        'product_description',
        'product_image',
        'status',
        'total',
        'created_at',
        'updated_at'
    ];

    protected $hidden = ['id','user_id', 'created_at', 'updated_at', 'otp','merchant_id',];

    public function user(){
        return $this->belongsTo(User::class);
    }

    public function merchant(){
        return $this->belongsTo(User::class, 'merchant_id', 'id');
    }
}
