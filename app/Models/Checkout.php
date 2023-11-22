<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Checkout extends Model
{
    use HasFactory;

    protected $fillable = ['status'];

    protected $hidden = ['id','merchant_id'];

    public static function boot(): void
    {
        parent::boot();
        self::creating(function ($model) {
            $model->uuid = Str::uuid()->toString();
        });
    }

    public function merchant(){
        return $this->belongsTo(User::class);
    }

    public function card_payment(): MorphOne
    {
        return $this->morphOne(CardPayment::class, 'card_paymentable');
    }
}
