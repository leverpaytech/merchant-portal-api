<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardPayment extends Model
{
    use HasFactory;

    protected $primaryKey = 'uuid';

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }
}
