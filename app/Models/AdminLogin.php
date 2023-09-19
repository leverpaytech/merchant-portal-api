<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class AdminLogin extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'email',
        'password',
        'first_name',
        'last_name',
        'gender',
        'address',
        'status',
        'last_seen_at',
        'phone',
        'passport',
        'updated_at',
        'created_at',
        'verify_email_status',
        'forgot_password_token'
    ];
    protected $hidden = [
        'id',
        'verify_email_token',
        'forgot_password_token',
        'verify_email_status',
        'password',
        'remember_token',
        'updated_at',
        'created_at',
    ];
    
}
