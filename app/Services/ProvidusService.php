<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ProvidusService
{
    // static $endpoint = env('PROVIDUS_BASEURL');
    // static $client_id = env('PROVIDUS_CLIENT_ID');
    // static $signature = env('PROVIDUS_X_AUTH_SIGNATURE');


    public static function generateDynamicAccount($account_name){
        // $data = [
        //     'account_name'=>$account_name,
        // ];
        // $response = Http::withHeaders([
        //     'accept'=>'application/json',
        //     'content-type'=>'application/json',
        //     'X-Auth-Signature'=>env('PROVIDUS_X_AUTH_SIGNATURE'),
        //     'Client-Id'=>env('PROVIDUS_CLIENT_ID')])->post(env('PROVIDUS_BASEURL')."/PiPCreateDynamicAccountNumber", $data)->json();
        // return $response;

        $data = array(
            'account_name'=>$account_name,
        );
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => env('PROVIDUS_BASEURL')."/PiPCreateDynamicAccountNumber",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30000,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => array(
                "Accept:*/*",
                "accept-language: en-US,en;q=0.8",
                "Content-Type: application/json",
                "X-Auth-Signature: ".env('PROVIDUS_X_AUTH_SIGNATURE'),
                "Client-Id: ".env('PROVIDUS_CLIENT_ID'),
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            return "cURL Error #:".$err;
        } else {
            return(json_decode($response));
        }
    }

    public static function generateReservedAccount($bvn,$account_name){
        // $response = Http::withHeaders([
        //     'accept'=>'application/json',
        //     'content-type'=>'application/json',
        //     'X-Auth-Signature'=>env('PROVIDUS_X_AUTH_SIGNATURE'),
        //     'Client-Id'=>env('PROVIDUS_CLIENT_ID')
        // ])->post(env('PROVIDUS_BASEURL')."/PiPCreateReservedAccountNumber", [
        //     'account_name'=>$account_name,
        //     'bvn'=>$bvn
        // ]);
        // return $response;

        $data = array(
            'account_name'=>$account_name,
            'bvn'=>$bvn
        );

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => env('PROVIDUS_BASEURL')."/PiPCreateReservedAccountNumber",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30000,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => array(
                "Accept:*/*",
                "accept-language: en-US,en;q=0.8",
                "Content-Type: application/json",
                "X-Auth-Signature: ".env('PROVIDUS_X_AUTH_SIGNATURE'),
                "Client-Id: ".env('PROVIDUS_CLIENT_ID'),
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            return "cURL Error #:".$err;
        } else {
            return(json_decode($response));
        }
    }

    public static function getBillsField($id){
        $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => env('PROVIDUS_BILLS_BASEURL')."/provipay/webapi/field/assigned/byBillId/{$id}",
                CURLOPT_USERPWD => env('PROVIDUS_BILLS_USERNAME') . ':'. env('PROVIDUS_BILLS_PASSWORD'),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30000,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_HTTPHEADER => array(
                    "Accept:*/*",
                    "accept-language: en-US,en;q=0.8",
                    "Content-Type: application/json",
                ),
            ));

            $response = curl_exec($curl);
            $err = curl_error($curl);

            curl_close($curl);

            if ($err) {
                return ['status' =>false, 'message' =>$err];
            } else {
                $decode=json_decode($response);
                if($decode->bill_id == 0){
                    return ['status' =>false, 'message' =>"Invalid bill identifier"];
                }
                return ['status' =>true, 'data'=>$decode];
            }
    }
}
