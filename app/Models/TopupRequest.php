<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TopupRequest extends Model
{
    use HasFactory;

    protected $hidden = ['id','user_id'];

    public static function boot(): void
    {
        parent::boot();
        self::creating(function ($model) {
            $model->uuid = Str::uuid()->toString();
        });
    }

    public function user(){
        return $this->belongsTo(User::class);
    }
}
