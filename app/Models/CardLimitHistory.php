<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CardLimitHistory extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'id',
        'card_type_id',
        'limit',
        'status',
        'created_at',
        'updated_at'
    ];

    protected $hidden = [
        'id',
        'created_at',
        'updated_at'
    ];
}
