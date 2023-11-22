<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\Uid\Ulid;

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

    public static function boot(): void
    {
        parent::boot();
        self::creating(function ($model) {
            $model->uuid = Ulid::generate();
        });
    }
}
