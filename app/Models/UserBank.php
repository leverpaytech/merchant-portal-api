<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserBank extends Model
{
    use HasFactory;
    protected $fillable = [
        'id',
        'user_id',
        'bank_id',
        'account_no',
        'staus',
        'created_at',
        'updated_at'
    ];

    protected $hidden = [
        'id',
        'user_id',
        'bank_id',
        'created_at',
        'updated_at'
    ];
}
