<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Card extends Model
{
    use HasFactory;

    // type: 1 - normal card, 2 - Gold card, 3 - Diamond card, 4 - Pink Lady, 5 - Enterprise card

    protected $hidden = [
        'id',
        'user_id',
        'pin',
        'created_at',
        'updated_at'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
