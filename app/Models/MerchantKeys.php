<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MerchantKeys extends Model
{
    use HasFactory;
    protected $hidden = ['id', 'user_id'];

    public function user()
    {
        return $this->belongsTo(User::class,'user_id');
    }

}
