<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Investment extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'uuid',
        'user_id',
        'amount',
        'created_at',
        'updated_at'
    ];

    protected $hidden =[
        'id',
        'uuid',
        'user_id',
    ];

    public static function boot(): void
    {
        parent::boot();
        self::creating(function ($model) {
            $model->uuid = Str::uuid()->toString();
        });
    }
}
