<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kyc extends Model
{
    // KYC Document Type
    // 1 - Passport, 2 - Government Issued Card, 3 - International Passport, 4 - Driver License,
    // 5 - Voter's Card, 6 - Residential Address, 7 - NIN, 8 - Utility Bill, 9 - BVN, 10 - Business name,
    // 11 - CAC RC Nuber,
    use HasFactory;
    protected $fillable = [
        'id',
        'user_id',
        'passport',
        'document_type_id',
        'id_card_front',
        'id_card_back',
        'country_id',
        'state_id',
        'place_of_birth',
        'nin',
        'residential_address',
        'utility_bill',
        'bvn',
        'business_address',
        'business_certificate',
        'rc_number',
        'card_type',
        'status',
        'created_at',
        'updated_at'
    ];

    //card_type 1=gold, 2=diamond 3=pink-lady 4=enterprise
    protected $hidden = ['id','card_type','document_type_id','country_id','state_id', 'user_id', 'created_at', 'updated_at'];
    
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id','id');
    }

    public function documentType()
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id','id');
    }

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id', 'id');
    }

    public function state()
    {
        return $this->belongsTo(State::class, 'state_id', 'id');
    }
}
