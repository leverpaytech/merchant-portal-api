<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentOption extends Model
{
    use HasFactory;
    protected $fillable = [
        'id',
        'name',
        'icon',
        'uuid',
        'status',
        'created_at',
        'updated_at'
    ];
}
