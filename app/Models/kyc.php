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
    protected $fillable = ['user_id','document_name','document_link','status'];

}
