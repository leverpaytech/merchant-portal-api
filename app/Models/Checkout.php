<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Checkout extends Model
{
    use HasFactory;

    public static function boot(): void
    {
        parent::boot();
        self::creating(function ($model) {
            $model->uuid = Str::uuid()->toString();
        });
    }

    public function merchant(){
        return $this->belongsTo(User::class);
    }
}