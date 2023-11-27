<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    use HasFactory;
    protected $fillable = [
        'id',
        'code_no',
        'status',
        'created_at',
        'updated_at'
    ];
    protected $hidden = ['updated_at'];
}
