<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ProvidusService
{
    // static $endpoint = env('PROVIDUS_BASEURL');
    // static $client_id = env('PROVIDUS_CLIENT_ID');
    // static $signature = env('PROVIDUS_X_AUTH_SIGNATURE');


    public static function generateDynamicAccount($account_name){
        $response = Http::withHeaders([
            'accept'=>'application/json',
            'content-type'=>'application/json',
            'X-Auth-Signature'=>env('PROVIDUS_X_AUTH_SIGNATURE'),
            'Client-Id'=>env('PROVIDUS_CLIENT_ID')
        ])->post(env('PROVIDUS_BASEURL')."/PiPCreateDynamicAccountNumber", [
            'account_name'=>$account_name,
        ]);
        return $response;
    }

    public static function generateReservedAccount($bvn,$account_name,){
        $response = Http::withHeaders([
            'accept'=>'application/json',
            'content-type'=>'application/json',
            'X-Auth-Signature'=>env('PROVIDUS_X_AUTH_SIGNATURE'),
            'Client-Id'=>env('PROVIDUS_CLIENT_ID')
        ])->post(env('PROVIDUS_BASEURL')."/PiPCreateReservedAccountNumber", [
            'account_name'=>$account_name,
            'bvn'=>$bvn
        ]);
        return $response;
    }
}
