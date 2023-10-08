<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ProvidusService
{
    static $endpoint = "http://154.113.16.142:8088/appdevapi/api";
    static $client_id = "dGVzdF9Qcm92aWR1cw==";
    static $signature = "be09bee831cf262226b426e39bd1092af84dc63076d4174fac78a2261f9a3d6e59744983b8326b69cdf2963fe314dfc89635cfa37a40596508dd6eaab09402c7";


    public static function generateDynamicAccount($account_name){
        $response = Http::withHeaders([
            'accept'=>'application/json',
            'content-type'=>'application/json',
            'X-Auth-Signature'=>self::$signature,
            'Client-Id'=>self::$client_id
        ])->post(self::$endpoint."/PiPCreateDynamicAccountNumber", [
            'account_name'=>$account_name,
        ]);
        return $response;
    }

    public static function generateReservedAccount($bvn,$account_name,){
        $response = Http::withHeaders([
            'accept'=>'application/json',
            'content-type'=>'application/json',
            'X-Auth-Signature'=>self::$signature,
            'Client-Id'=>self::$client_id
        ])->post(self::$endpoint."/PiPCreateReservedAccountNumber", [
            'account_name'=>$account_name,
            'bvn'=>$bvn
        ]);
        return $response;
    }
}
