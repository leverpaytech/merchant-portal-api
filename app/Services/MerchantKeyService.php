<?php

namespace App\Services;

use App\Models\MerchantKeys;
use Illuminate\Support\Str;

class MerchantKeyService
{
    public static function createKeys($userId){
        $test_public="test_pk_".strtolower(Str::random(30));
        $test_secret="test_sk_".strtolower(Str::random(30));
        $live_public=strtolower(Str::random(40));
        $live_secret=strtolower(Str::random(40));

        $merchantKeys = new MerchantKeys();
        $merchantKeys->user_id = $userId;
        $merchantKeys->test_public_key = $test_public;
        $merchantKeys->test_secret_key = $test_secret;
        $merchantKeys->live_public_key = $live_public;
        $merchantKeys->live_secret_key = $live_secret;
        $merchantKeys->save();

        return $merchantKeys;
    }
}
