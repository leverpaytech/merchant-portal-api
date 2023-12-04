<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Transfer extends Model
{
    use HasFactory;

    protected $hidden = [
        'id','otp', 'user_id', 'receiver_id'];

    public static function boot(): void
    {
        parent::boot();
        self::creating(function ($model) {
            $model->uuid = Str::uuid()->toString();
        });
    }

    public function sender(){
        return $this->belongsTo(User::class,'user_id','id');
    }

    public function recipient()
    {
        return $this->belongsTo(User::class, 'receiver_id','id');
    }
}
