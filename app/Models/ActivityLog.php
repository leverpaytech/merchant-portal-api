<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = ['activity','created_at','updated_at','user_id'];

    public static function createActivity($data)
    {
        return self::create($data);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
