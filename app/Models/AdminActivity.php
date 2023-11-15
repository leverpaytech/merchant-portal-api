<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminActivity extends Model
{
    use HasFactory;

    protected $fillable = ['activity','created_at','updated_at','admin_id'];

    public static function createActivity($data)
    {
        return self::create($data);
    }

    public function admin()
    {
        return $this->belongsTo(AdminLogin::class);
    }
}
