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

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'gender',
        'address',
        'status',
        'last_seen_at',
        'phone',
        'email',
        'dob',
        'picture',
        'country_id',
        'state_id',
        'city_id',
        'zip_code',
        'password',
        'passport',
        'updated_at',
        'created-at',
        'role_id',
        'kyc_status',
        'verify_email_status',
        'verify_email_token',
        'forgot_password_token'
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
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
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
