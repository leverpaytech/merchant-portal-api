<?php

namespace App\Services;

/**
 * Class VfdService.
 */
class VfdService
{
    // public static $baseUrl='https://api-devapps.vfdbank.systems/vtech-wallet/api/v1.1/billspaymentstore/';
    // public static $authUrl='https://api-devapps.vfdbank.systems/vfd-tech/baas-portal/v1/';

    public static $baseUrl='https://api-apps.vfdbank.systems/vtech-wallet/api/v1/billspaymentstore/';
    public static $authUrl='https://api-apps.vfdbank.systems/vfd-tech/baas-portal/v1/';

    // public static $authUrl;
    // public static $baseUrl;

    // public function __construct()
    // {
    //     self::$authUrl = config('services.vfd.test_auth_url');
    //     self::$baseUrl = config('services.vfd.test_base_url');
    // }

    public static function generateAccessToken()
    {
        $url = self::$authUrl . 'baasauth/token';

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        //test
        // $data = [
        //     'consumerKey' => 'hAKkhUvxa6bKJCUVVJbXyjtwJARz',
        //     'consumerSecret' => 'N5gOiQqhBNrcXEsp4zoasibUeUv3',
        //     'validityTime' => '-1',
        // ];
        //live
        $data = [
            'consumerKey' => 'SuZ1Cc7ZAbYL5XnSqsimXuN6r4cD',
            'consumerSecret' => '7hf0ykJT5NZUXiiq4VmJ039wrov7',
            'validityTime' => '-1',
        ];
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            return curl_error($ch);
        }
        curl_close($ch);
        // Handle the response as needed
        return  $response;
    }

    public static function getBillerCategory($accessToken)
    {
        $url = self::$baseUrl . 'billercategory';

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'AccessToken: ' . $accessToken, // Include the actual access token
        ];

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        // Don't set CURLOPT_POSTFIELDS for a GET request
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            return curl_error($ch);
        }

        curl_close($ch);

        // Handle the response as needed
        return $response;
    }

    // Example usage:
    //$accessToken = 'YOUR_ACTUAL_ACCESS_TOKEN';
    //$response = getBillerCategory($accessToken);
    //echo $response;


    public static function getBillerCategoryList($accessToken,$categoryName)
    {
        $categoryName=urlencode($categoryName);
        $url = self::$baseUrl . "billerlist?categoryName={$categoryName}";
        
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'AccessToken: ' . $accessToken, // Include the actual access token
        ];

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            return curl_error($ch);
        }

        curl_close($ch);
        // Handle the response as needed
        return $response;
    }

    public static function getBillerItems($accessToken,$billerId,$divisionId,$productId)
    {
        $billerId=urlencode($billerId);
        $divisionId=urlencode($divisionId);
        $productId=urlencode($productId);
        
        $url = self::$baseUrl . "billerItems?billerId={$billerId}&divisionId={$divisionId}&productId={$productId}";
        //return $url;
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'AccessToken: ' . $accessToken, // Include the actual access token
        ];

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            return curl_error($ch);
        }
        curl_close($ch);
        // Handle the response as needed
        return $response;
    }

    public static function payBill($accessToken,$phone,$amount,$division,$paymentItem,$productId,$billerId,$reference)
    {
    
        $url = self::$baseUrl . 'pay';
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'AccessToken: ' . $accessToken, // Include the actual access token
        ];

        $data = [
            'customerId' => $phone,
            'amount' => $amount,
            'division' => $division,
            'paymentItem' => $paymentItem,
            'productId' => $productId,
            'billerId' => $billerId,
            'reference' => $reference
        ];

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            return curl_error($ch);
        }
        curl_close($ch);
        // Handle the response as needed
        return  $response;
    }

    public static function checkTransaction($accessToken,$tReference)
    {
        //$tReference=urlencode($tReference);
        $url = self::$baseUrl . "/transactionStatus?transactionId={$tReference}";
        
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'AccessToken: ' . $accessToken, // Include the actual access token
        ];

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            return curl_error($ch);
        }

        curl_close($ch);
        // Handle the response as needed
        return $response;
    }
}
