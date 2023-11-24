<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserReferral extends Model
{
    use HasFactory;
    use HasFactory;
    protected $fillable = [
        'id',
        'user_id',
        'referral_id',
        'status',
        'created_at',
        'updated_at'
    ];

    protected $hidden = [
        'id',
        'user_id',
        'referral_id',
        'created_at',
        'updated_at'
    ];

    public function user(){
        return $this->belongsTo(User::class);
    }

    public function referer()
    {
        return $this->belongsTo(User::class, 'referral_id', 'id');
    }
}
