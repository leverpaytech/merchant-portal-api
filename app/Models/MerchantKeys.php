<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MerchantKeys extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'test_public_key',
        'test_secrete_key',
        'live_public_key',
        'live_secrete_key',
        'stage',
        'status',
        'updated_at',
        'created-at'
    ];

    protected $hidden = ['id', 'user_id'];

    public function user()
    {
        return $this->belongsTo(User::class,'user_id');
    }

}
