<?php

namespace App\Services;
use Illuminate\Support\Facades\Http;

/**
 * Class QoreIdService.
 */
class QoreIdService
{
    public static function generateAccessToken()
    {
        // $clientId = env('QOREID_TEST_CLIENT_ID');
        // $clientSecret = env('QOREID_TEST_CLIENT_SECRET');

        $clientId = env('QOREID_LIVE_CLIENT_ID');
        $clientSecret = env('QOREID_LIVE_CLIENT_SECRET');
        
        
        // Making the API request
        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->post(env('QOREID_BASE_URL').'/token', [
            'clientId' => $clientId,
            'secret' => $clientSecret,
        ]);

        // Check for errors
        if ($response->failed()) {
            // Handle the error (log it, throw an exception, etc.)
            return ['error' => 'Request failed: ' . $response->body()];
        }

        // Return the decoded response
        $data=$response->json();
        return $data['accessToken'];
    }

    public static function verifyNIN($nin, $firstname, $lastname, $accessToken)
    {
        $url = env('QOREID_BASE_URL')."/v1/ng/identities/nin/{$nin}";
        
        $fields = [
            'firstname' => $firstname,
            'lastname' => $lastname
        ];

        // Make the API request with first_name and Last_name
        // $response = Http::withHeaders([
        //     'Accept' => 'application/json',
        //     'Authorization' => 'Bearer ' . $accessToken,
        //     'Content-Type' => 'application/json',
        // ])->post($url, $fields);
        
        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
        ])->post($url);

        // Handle error if response is not successful
        if ($response->failed()) {
            return ['error' => 'Request failed: ' . $response->body()];
        }

        // Return the JSON decoded response
        return $response->json();
    }

    public static function verifyBVN($bvn, $firstname, $lastname, $accessToken)
    {
        $url = env('QOREID_BASE_URL')."/v1/ng/identities/bvn-match/{$bvn}";
        
        $fields = [
            'firstname' => $firstname,
            'lastname' => $lastname
        ];

        // Make the API request
        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
        ])->post($url, $fields); 

        // Handle error if response is not successful
        if ($response->failed()) {
            return ['error' => 'Request failed: ' . $response->body()];
        }

        // Return the JSON decoded response
        return $response->json();
    }

}
