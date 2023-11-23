<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardPayment extends Model
{
    use HasFactory;

    protected $hidden = ['id', 'card_paymentable_type', 'card_paymentable_id','card_id','otp'];

    protected $primaryKey = 'uuid';

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    // this morph class is for incase we have another use case for paying with card ( currently we use it only for checkout)
    public function card_paymentable()
    {
        return $this->morphTo();
    }

}
