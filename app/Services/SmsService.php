<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SmsService
{
    public static function sendSms($message, $phoneNumber)
    {   
        $data = [
            "api_key" => env('TERMII_API_KEY'), //"Your API key"
            "to" => $phoneNumber,
            "from" => "N-Alert", //talert
            "sms" => $message,
            "type" => "plain",
            "channel" => "dnd",
            "encoding" => "UTF-8"
        ];

        // Make the POST request to the Termii API
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post(env('TERMII_API_BASE_URL').'/api/sms/send', $data);

        // Check the response status
        if ($response->successful()) {
            return $response->json(); // Return or process the JSON response
        } else {
            return false;
            // return response()->json([
            //     'error' => 'Failed to send SMS',
            //     'details' => $response->body(),
            // ], $response->status());
        }
    }

    // public static function sendSms($message, $phoneNumber){
    //     $response = Http::withHeaders([
    //         'accept'=>'application/json',
    //         'content-type'=>'application/json'
    //     ])->post(env('TERMII_API_BASE_URL').'/api/sms/send', array(
    //         'api_key' => env('TERMII_API_KEY'),
    //         'to'=>$phoneNumber,
    //         'from'=>'N-Alert',
    //         'sms'=>$message,
    //         'type'=>'plain',
    //         'channel'=>'dnd',

    //     ));
    //     return $response;
    // }

    // public static function sendMail($message,$html, $subject, $to,)
    // {
    //     $response = Http::withBasicAuth(env('MAILJET_API_KEY'), env('MAILJET_SECRET_KEY'))->withHeaders([
    //         'accept'=>'application/json',
    //         'content-type'=>'application/json'
    //     ])->post('https://api.mailjet.com/v3.1/send', [
    //         "Messages"=>[
    //             ['From' => [
    //                 'Email' => 'development@leverpay.io',
    //                 'Name' => env('APP_NAME')
    //             ],
    //             'To' => [
    //                 [
    //                     'Email' => $to,
    //                 ]
    //             ],
    //             'Subject' => $subject,
    //             'TextPart' => $message,
    //             'HTMLPart'=> $html
    //             ]
    //         ]
    //     ]);

    //     return $response;
    // }
}
