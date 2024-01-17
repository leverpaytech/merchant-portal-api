<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\Uid\Ulid;
use Illuminate\Support\Facades\DB;
use App\Models\Transaction;
use App\Services\WalletService;

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
                "Accept:application/json",
                "accept-language: en-US,en;q=0.8",
                "Content-Type: application/json",
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);
        $response = json_decode($response);

        if ($err) {
            return $this->sendError($err,[], 400);
        } else {
            return $this->successfulResponse($response,'');
            // return $response;
            // if($response->successful){
            //     return $this->successfulResponse($response,'');
            // }else{
            //     return $this->sendError($response->message,[], 400);
            // }
        }
    }

    public function buyAirtime(Request $request){
        $this->validate($request, [
            'phone'=> 'required',
            'amount'=>'required|numeric|min:50',
            'bill_id'=>'required|numeric'
        ]);
        $user = Auth::user();
        if(!$user->wallet || $user->wallet->withdrawable_amount < $request['amount']){
            return $this->sendError("Insufficient wallet balance",[], 400);
        }


        try{
            DB::beginTransaction();
            $ext = 'LP_'.Ulid::generate();

            $transaction = new Transaction();
            $transaction->user_id = $user->id;
            $transaction->reference_no = $ext;
            $transaction->tnx_reference_no	= $ext;
            $transaction->amount =$request['amount'];
            $transaction->balance = floatval($user->wallet->withdrawable_amount) - floatval($request['amount']);
            $transaction->type = 'debit';
            $transaction->merchant = 'airtime';
            $transaction->status = 1;

            $details = [
                "bill_phone"=>$request['phone'],
                "bill_id"=>$request['bill_id'],
                "bill_provider"=>"providus bank"
            ];
            $transaction->transaction_details = json_encode($details);
            $transaction->save();

            WalletService::subtractFromWallet($user->id, $request['amount']);

            $requestData=array(
                "inputs"=>[
                    [
                        "value"=> $request->phone,
                        "key"=>"customerId"
                    ],
                    [
                        "value"=> $request->amount,
                        "key"=>"amount"
                    ],
                ],
                "customerAccountNo"=>env('PROVIDUS_LEVERPAY_ACCOUNT_NO'),
                "billId"=>$request['bill_id'],
                "channel_ref"=>$ext
            );
            $postData= json_encode($requestData);
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => env('PROVIDUS_BILLS_BASEURL')."/provipay/webapi/validate/{$request['bill_id']}/customer",
                CURLOPT_USERPWD => env('PROVIDUS_BILLS_USERNAME') . ':'. env('PROVIDUS_BILLS_PASSWORD'),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30000,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => $postData,
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
                DB::rollBack();
                return $this->sendError($err,[], 400);
            } else {

                DB::commit();
                return $this->successfulResponse(json_decode($response),'');
            }

        }catch(\Exception $e){
            DB::rollBack();
            return $this->sendError($e->getMessage(),[],400);
        }
    }

    public function getDataDetails($bill_id){
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => env('PROVIDUS_BILLS_BASEURL')."/provipay/webapi/field/assigned/byBillId/{$bill_id}",
            CURLOPT_USERPWD => env('PROVIDUS_BILLS_USERNAME') . ':'. env('PROVIDUS_BILLS_PASSWORD'),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30000,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => array(
                "Accept:application/json",
                "accept-language: en-US,en;q=0.8",
                "Content-Type: application/json",
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);
        $response = json_decode($response);

        if ($err) {
            return $this->sendError($err,[], 400);
        } else {
            $list = [];
            if(property_exists($response, 'fields')){
                foreach ($response->fields as $value) {
                    if($value->key == 'bouquet'){
                        $list = $value;
                    }
                }
                if(is_array($list) && count($list) < 1){
                    return $this->sendError('Invalid data subscription',[], 400);
                }
                return $this->successfulResponse($list->list->items,'');
            }else{
                return $this->sendError('Invalid data subscription',[], 400);
            }
        }
    }

    public function getData(){
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => env('PROVIDUS_BILLS_BASEURL')."/provipay/webapi/bill/assigned/byCategoryId/3",
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
        $response = json_decode($response);
        if ($err) {
            return $this->sendError($err,[], 400);
        } else {
            return $this->successfulResponse($response,'');
        }
    }

    public function buyData(Request $request){
        $this->validate($request, [
            'phone'=> 'required',
            'bill_id'=> 'required|numeric|min:1',
            'data_id'=>'required|numeric'
        ]);

        $getList = $this->getDataDetails($request->bill_id);
        $getList = $getList->getOriginalContent();

        if(!$getList['success']){
            return $this->sendError($getList['message'],[], 400);
        }

        $data = [];
        foreach($getList['data'] as $value) {
            if($value->id == $request->data_id){
                $data = $value;
            }
        }
        if(count($data) < 1){
            return $this->sendError("Invalid data subscription selected",[], 400);
        }

        $user = Auth::user();
        if(!$user->wallet || $user->wallet->withdrawable_amount < $data->amount){
            return $this->sendError("Insufficient wallet balance",[], 400);
        }

        try{
            DB::beginTransaction();
            $ext = 'LP_'.Ulid::generate();

            $transaction = new Transaction();
            $transaction->user_id = $user->id;
            $transaction->reference_no = $ext;
            $transaction->tnx_reference_no	= $ext;
            $transaction->amount =$request['amount'];
            $transaction->balance = floatval($user->wallet->withdrawable_amount) - floatval($data->amount);
            $transaction->type = 'debit';
            $transaction->merchant = 'data';
            $transaction->status = 1;

            $details = [
                "bill_phone"=>$request['phone'],
                "bill_id"=>$request['bill_id'],
                "data_id"=>$request['data_id'],
                "bill_provider"=>"providus bank"
            ];
            $transaction->transaction_details = json_encode($details);
            $transaction->save();

            WalletService::subtractFromWallet($user->id, $data->amount);

            $requestData=array(
                "inputs"=>[
                    [
                        "value"=> $request->phone,
                        "key"=>"customerId"
                    ],
                    [
                        "value"=> $request->data_id,
                        "key"=>"bouquet"
                    ],
                ],
                "customerAccountNo"=>env('PROVIDUS_LEVERPAY_ACCOUNT_NO'),
                "billId"=>$request['bill_id'],
                "channel_ref"=>$ext
            );
            $postData= json_encode($requestData);
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => env('PROVIDUS_BILLS_BASEURL')."/provipay/webapi/makepayment",
                CURLOPT_USERPWD => env('PROVIDUS_BILLS_USERNAME') . ':'. env('PROVIDUS_BILLS_PASSWORD'),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30000,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => $postData,
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
                DB::rollBack();
                return $this->sendError($err,[], 400);
            } else {

                DB::commit();
                return $this->successfulResponse(json_decode($response),'');
            }

        }catch(\Exception $e){
            DB::rollBack();
            return $this->sendError($e->getMessage(),[],400);
        }
    }
}
