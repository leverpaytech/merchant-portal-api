<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\Uid\Ulid;

class LeverPayAccountNo extends Model
{
    use HasFactory;

    protected $hidden = [
        "id",
        "bank",
        'account_number',
        'balance',
        'account_name',
        "created_at",
        "updated_at"
    ];

    public static function boot(): void
    {
        parent::boot();
        self::creating(function ($model) {
            $model->uuid = Ulid::generate();
        });
    }
}
