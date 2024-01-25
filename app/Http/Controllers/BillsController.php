<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\Uid\Ulid;
use Illuminate\Support\Facades\DB;
use App\Models\Transaction;
use App\Models\User;
use App\Services\WalletService;
use App\Models\ActivityLog;
use App\Services\ProvidusService;

class BillsController extends BaseController
{
    /**
     * @OA\Get(
     ** path="/api/v1/user/bills/get-airtime",
     *   tags={"Providus Bill Payment"},
     *   summary="Get Airtime",
     *   operationId="Get Airtime",
     * 
     *   @OA\Response(
     *      response=200,
     *       description="Success",
     *     ),
     *     security={
     *       {"bearer_token": {}}
     *     }
     *
     *)
     **/
    public function getAirtime()
    {
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

    /**
     * @OA\Post(
     ** path="/api/v1/user/bills/buy-airtime",
     *   tags={"Providus Bill Payment"},
     *   summary="Buy Airtime",
     *   operationId="Buy Airtime",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"phone","amount","bill_id"},
     *              @OA\Property( property="phone", type="string"),
     *              @OA\Property( property="amount", type="string", description="amount to acquire service"),
     *              @OA\Property( property="bill_id", type="string")
     *          ),
     *      ),
     *   ),
     *
     *   @OA\Response(
     *      response=200,
     *       description="Success",
     *      @OA\MediaType(
     *           mediaType="application/json",
     *      )
     *   ),
     *   @OA\Response(
     *      response=401,
     *       description="Unauthenticated"
     *   ),
     *   @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     *   @OA\Response(
     *      response=404,
     *      description="not found"
     *   ),
     *   @OA\Response(
     *      response=403,
     *      description="Forbidden"
     *   ),
     *   security={
     *       {"bearer_token": {}}
     *   }
     *)
     **/
    public function buyAirtime(Request $request)
    {
        $data2['activity']="Buy Airtime Attempt - User: " . Auth::user()->id .'-'.Auth::user()->first_name. ' '. Auth::user()->last_name. ' Amount:'. $request['amount'];
        $data2['user_id']=Auth::user()->id;

        ActivityLog::createActivity($data2);

        $this->validate($request, [
            'phoneNumber'=> 'required',
            'amount'=>'required|numeric|min:50|max:100',
            'bill_id'=>'required|numeric'
        ]);

        $user = Auth::user();
        $ip = User::where('id', $user->id)->update([
            'zip_code' => $request->getClientIp()
        ]);
        if(!$user->wallet || $user->wallet->withdrawable_amount < $request['amount']){
            return $this->sendError("Insufficient wallet balance",[], 400);
        }

        $field = ProvidusService::getBillsField($request->bill_id);

        if(!$field['status']){
            return $this->sendError($field['message'],[], 400);
        }

        $serviceType = '';
        foreach($field['data']->fields as $value){
            if($value->key == 'serviceCode'){
                $serviceType = $value->list->items[0]->id;
            }
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
                "bill_phone"=>$request['phoneNumber'],
                "bill_id"=>$request['bill_id'],
                "bill_provider"=>"providus bank"
            ];
            $transaction->transaction_details = json_encode($details);
            $transaction->save();

            WalletService::subtractFromWallet($user->id, $request['amount']);

            $requestData=array(
                "inputs"=>[
                    [
                        "value"=> $request->phoneNumber,
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
                CURLOPT_URL => env('PROVIDUS_BILLS_BASEURL')."/provipay/webapi/makepayment/{$request['bill_id']}/customer",
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

    /**
     * @OA\Get(
     ** path="/api/v1/user/bills/get-data-details/{bill_id}",
     *   tags={"Providus Bill Payment"},
     *   summary="Get Data Details",
     *   operationId="Get Data Details",
     *
     * * @OA\Parameter(
     *      name="bill_id",
     *      in="path",
     *      required=true,
     *      @OA\Schema(
     *           type="string",
     *      )
     *   ),
     * 
     *   @OA\Response(
     *      response=200,
     *       description="Success",
     *     ),
     *     security={
     *       {"bearer_token": {}}
     *     }
     *
     *)
     **/
    public function getDataDetails($bill_id)
    {
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

    /**
     * @OA\Get(
     ** path="/api/v1/user/bills/get-data",
     *   tags={"Providus Bill Payment"},
     *   summary="Get Data",
     *   operationId="Get Data",
     * 
     *   @OA\Response(
     *      response=200,
     *       description="Success",
     *     ),
     *     security={
     *       {"bearer_token": {}}
     *     }
     *
     *)
     **/
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

    /**
     * @OA\Post(
     ** path="/api/v1/user/bills/buy-data",
     *   tags={"Providus Bill Payment"},
     *   summary="Buy Data",
     *   operationId="Buy Data",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"phone","data_id","bill_id"},
     *              @OA\Property( property="phone", type="string"),
     *              @OA\Property( property="data_id", type="string"),
     *              @OA\Property( property="bill_id", type="string")
     *          ),
     *      ),
     *   ),
     *
     *   @OA\Response(
     *      response=200,
     *       description="Success",
     *      @OA\MediaType(
     *           mediaType="application/json",
     *      )
     *   ),
     *   @OA\Response(
     *      response=401,
     *       description="Unauthenticated"
     *   ),
     *   @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     *   @OA\Response(
     *      response=404,
     *      description="not found"
     *   ),
     *   @OA\Response(
     *      response=403,
     *      description="Forbidden"
     *   ),
     *   security={
     *       {"bearer_token": {}}
     *   }
     *)
     **/
    public function buyData(Request $request)
    {
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
