<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClaimedBonus extends Model
{
    use HasFactory;
    protected $fillable = [
        'id',
        'user_id',
        'amount',
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
