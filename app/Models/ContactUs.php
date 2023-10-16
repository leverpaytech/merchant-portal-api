<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactUs extends Model
{
    use HasFactory;
    protected $fillable = [
        'id',
        'uuid',
        'email',
        'subject',
        'message',
        'reply',
        'status',
        'created_at',
        'updated_at'
    ];

    protected $hidden = [
        'id'
    ];        
}
