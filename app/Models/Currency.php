<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'currency_code',
        'created_at',
        'updated_at'
    ];

    public static function createCurrency($data)
    {
        return self::create($data);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'currency_user');
    }

}
