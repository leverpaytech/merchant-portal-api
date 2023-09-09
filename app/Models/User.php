<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    // Do not Delete
    // Role_id
    // 0 - normal user
    // 1 - Merchant

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'email',
        'other_email',
        'primary_email',
        'password',
        'first_name',
        'last_name',
        'other_name',
        'gender',
        'address',
        'status',
        'last_seen_at',
        'phone',
        'other_phone',
        'primary_phone',
        'dob',
        'country_id',
        'state_id',
        'city_id',
        'role_id',
        'zip_code',
        'passport',
        'updated_at',
        'created_at',
        'kyc_status',
        'verify_email_status',
        'verify_email_token',
        'forgot_password_token',
        'change_email_phone_token'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'id',
        'verify_email_token',
        'forgot_password_token',
        'change_email_phone_token',
        'country_id',
        'state_id',
        'city_id',
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
        'updated_at',
        'created_at',
        // 'role_id'
    ];
    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    // protected $appends = [
    //     'profile_photo_url',
    // ];

    public function currencies()
    {
        return $this->belongsToMany(Currency::class, 'currency_user');
    }

    public function merchant()
    {
        return $this->hasOne(Merchant::class);
    }

    public function card()
    {
        return $this->hasOne(Card::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function wallet()
    {
        return $this->belongsTo(Wallet::class, 'id','user_id');
    }

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function state()
    {
        return $this->belongsTo(State::class, 'state_id');
    }
    public function city()
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function merchantKeys()
    {
        return $this->belongsTo(MerchantKeys::class, 'id','user_id');
    }

    public function createUser($data)
    {
        /**
         * @var User $user
         */
        $user = self::create($data);
        return $user;
    }

    public function activate($id)
    {
       $user = self::find($id);
       //$user->status = true;
       $user->status = 2;
       $user->save();
       return $user;
    }

    public function deactivate($id)
    {
       $user = self::find($id);
       $user->status = false;
       $user->save();
       return $user;
    }

    public function updateUser($data,$id)
    {
        $user = self::find($id);
        $user->update($data);
        $user->save();
        return $user;
    }

    public function findUserByEmail($email)
    {
        return self::where('email', $email)->first();
    }

}
