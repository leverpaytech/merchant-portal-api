<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuickTellerDiscount extends Model
{
    use HasFactory;
    protected $fillable = [
        'id',
        'uuid',
        'category',
        'biller',
        'percent',
        'status',
        'created_at',
        'updated_at'
    ];

    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    public static function boot(): void
    {
        parent::boot();
        self::creating(function ($model) {
            // Check if we are in a seeding environment
            if (!app()->runningInConsole() || !app()->runningUnitTests()) {
                $model->uuid = Str::uuid()->toString();
            }
        });
    }
}
