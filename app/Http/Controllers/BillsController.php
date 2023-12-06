<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class BillsController extends BaseController
{
    public function getAirtime(){
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => env('PROVIDUS_BILLS_BASEURL')."/provipay/webapi/bill/assigned/byCategoryId/1",
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
            return $this->sendError($err,[], 400);
        } else {
            return $this->successfulResponse(json_decode($response),'');
        }
    }

    public function buyAirtime(Request $request){
        $this->validate($request, [
            'customerId'=> 'required',
            'amount'=>'required|numeric',
            'bill_id'=>'required|numeric'
        ]);

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => env('PROVIDUS_BILLS_BASEURL')."/provipay/webapi/field/assigned/byBillId/{$request['bill_id']}",
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
            return $this->sendError($err,[], 400);
        } else {
            return $this->successfulResponse(json_decode($response),'');
        }
    }
}
