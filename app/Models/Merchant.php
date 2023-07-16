<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Merchant extends Model
{
    use HasFactory;

    
    protected $fillable = [
        'user_id',
        'business_name',
        'business_address',
        'business_phone'
    ];

    protected $hidden = ['id'];
}
