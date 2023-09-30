<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    use HasFactory;
    protected $fillable = [
        'country_code',
        'country_name',
        'created_at',
        'updated_at'
    ];

    protected $hidden = [

        'created_at',
        'updated_at'
    ];
}
