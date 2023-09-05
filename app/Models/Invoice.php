<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;
    protected $fillable = [
        'id',
        'uuid',
        'user_id',
        'product_name',
        'price',
        'product_description',
        'product_image',
        'status',
        'created_at',
        'updated_at'
    ];

    protected $hidden = ['id', 'created_at', 'updated_at'];
}
