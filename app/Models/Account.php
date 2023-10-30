<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    use HasFactory;

    protected $hidden = [
        "id",
        "user_id",
        'type',
        'balance',
        "updated_at",
        "status",
        "model_id",
    ];
}
