<?php

namespace App\Services;
use Illuminate\Support\Facades\Http;

/**
 * Class QuickTellerService.
 */
class QuickTellerService
{
    public static function generateAccessToken()
    {
        $clientId = env('QUICKTELLER_CLIENT_ID');
        $clientSecret = env('QUICKTELLER_CLIENT_SECRET');
        
        $credentials = base64_encode($clientId . ":" . $clientSecret);

        $response = Http::asForm()->withHeaders([
            'Authorization' => 'Basic ' . $credentials,
            'Content-Type' => 'application/x-www-form-urlencoded',
        ])->post(env('QUICKTELLER_AUTH_URL'), [
            'grant_type' => 'client_credentials',
            'scope' => 'profile'
        ]);

        if ($response->successful()) {
            // Request was successful, handle the response
            $data = $response->json();
            // Access the access token
            return $accessToken = $data['access_token'];

        } else {
            // Request failed, handle the error
            return $response->status();
            // Handle the error based on the status code
        }
    }

    public static function billers($accessToken)
    {
        $curlInit = curl_init();

         curl_setopt($curlInit, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: ' . 'Bearer ' . $accessToken,
            'TerminalID: 2LCN0001'//3pbl0001
        ));

        curl_setopt($curlInit, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlInit, CURLOPT_URL, env('QUICKTELLER_BASE_URL').'/services');

        $response = curl_exec($curlInit);
        curl_close($curlInit);
        
        return $response;
    }

    public static function billersCategories($accessToken)
    {
        $curlInit = curl_init();

         curl_setopt($curlInit, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: ' . 'Bearer ' . $accessToken,
            'TerminalID: 3LCN0001' // 3pbl0001
        ));

        curl_setopt($curlInit, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlInit, CURLOPT_URL, env('QUICKTELLER_BASE_URL').'/services/categories');

        $response = curl_exec($curlInit);
        curl_close($curlInit);
        
        return $response;
    }

    public static function billersByCategoryId($accessToken,$categoryId)
    {

        $curlInit = curl_init();

        curl_setopt($curlInit, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: ' . 'Bearer ' . $accessToken,
            'TerminalID: 3LCN0001'
        ));

        curl_setopt($curlInit, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlInit, CURLOPT_URL, env('QUICKTELLER_BASE_URL')."/services/?categoryId=".$categoryId);

        $response = curl_exec($curlInit);
        curl_close($curlInit);
        
        return $response;
    }

    public static function billerPaymentItems($accessToken,$serviceId)
    {

        $curlInit = curl_init();
        curl_setopt($curlInit, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: ' . 'Bearer ' . $accessToken,
            'TerminalID: 3LCN0001'
        ));

        curl_setopt($curlInit, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlInit, CURLOPT_URL, env('QUICKTELLER_BASE_URL')."/services/options?serviceid=".$serviceId);

        $response = curl_exec($curlInit);
        curl_close($curlInit);
        
        return $response;
    }

    public static function  sendBillPayment($accessToken,$paymentCode,$customerId,$customerEmail,$customerMobile,$amount,$refrenceNo)
    {
        $curl = curl_init();
        $fieldsString = json_encode(array(
            'customerId' => $customerId,
            'customerMobile' => $customerMobile,
            'customerEmail' => $customerEmail,
            'amount' => $amount,
            'paymentCode' => $paymentCode,
            'requestReference' => $refrenceNo
        ));
        return $fieldsString;
        /*{
            "PaymentCode": "48001",
            "CustomerId": "0000000001",
            "CustomerEmail": "iswtester2@yahoo.com",
            "CustomerMobile": "2348056731575",
            "Amount": "1460000",
            "requestReference": "LI3100762G67W844U3U0J"
        }*/

        curl_setopt_array($curl, array(
        CURLOPT_URL => env('QUICKTELLER_BASE_URL').'/Transactions/',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $fieldsString,
        CURLOPT_HTTPHEADER => array(
            'terminalId: 3LCN0001',
            'Authorization: Bearer '.$accessToken,
            'Content-Type: application/json',
        ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        return $response;
        
    }

    public static function validateCustomer($accessToken,$paymentCode,$customerId)
    {

        $curl = curl_init(); 
        // $fields = array(
        //     "customers" => array(
        //         array(
        //             "PaymentCode" => "0488051528",
        //             "CustomerId" => "08124888436"
        //         )
        //     ),
        //     "TerminalId" => "3pbl"
        // );
        $fields = array(
            "customers" => array(
                array(
                    "PaymentCode" => $paymentCode,
                    "CustomerId" => $customerId
                )
            ),
            "TerminalId" => "3LCN0001" //3pbl
        );

        $fieldsString = json_encode($fields);

        curl_setopt_array($curl, array(
            CURLOPT_URL => env('QUICKTELLER_BASE_URL').'/Transactions/validatecustomers',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $fieldsString,
            CURLOPT_HTTPHEADER => array(
                'TerminalId: 3LCN0001', // Replace <your_terminal_id> with your actual terminal ID
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        return $response;

    }

    public static function getTransaction($accessToken,$transRef)
    {
        $curlInit = curl_init();
        curl_setopt($curlInit, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: ' . 'Bearer ' . $accessToken,
            'TerminalID: 3LCN0001'
        ));

        curl_setopt($curlInit, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlInit, CURLOPT_URL, env('QUICKTELLER_BASE_URL')."/Transactions?requestRef=".$transRef);

        $response = curl_exec($curlInit);
        curl_close($curlInit);
        
        return $response;
    }
}
