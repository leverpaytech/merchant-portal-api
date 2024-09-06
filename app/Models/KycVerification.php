<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class KycVerification extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'user_id',
        'phone',
        'email',
        'nin',
        'bvn',
        'nin_details',
        'bvn_details',
        'contact_address',
        'proof_of_address',
        'live_face_verification',
        'phone_verified_at',
        'email_verified_at',
        'nin_verified_at',
        'bvn_verified_at',
        'address_verified_at',
        'live_face_verified_at',
        'status',
        'admin_comment',
        'phone_verification_code',
        'email_verification_code',
        'extra',
        'created_at',
        'updated_at'
    ];

    protected $hidden = [
        'id',
        'user_id',
        'phone_verified_at',
        'email_verified_at',
        'nin_verified_at',
        'bvn_verified_at',  
        'address_verified_at',
        'live_face_verified_at',
        'created_at', 
        'updated_at'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id','id');
    }

    public static function boot(): void
    {
        parent::boot();
        self::creating(function ($model) {
            $model->uuid = Str::uuid()->toString();
        });
    }
}
