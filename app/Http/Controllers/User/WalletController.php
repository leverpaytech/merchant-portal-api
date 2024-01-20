<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\BaseController;
use App\Http\Controllers\Controller;
use App\Mail\GeneralMail;
use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\TopupRequest;
use App\Models\User;
use App\Models\{Wallet,Invoice};
use App\Models\Transaction;
use App\Models\Transfer;
use App\Services\ProvidusService;
use App\Services\SmsService;
use App\Services\ZeptomailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Webpatser\Uuid\Uuid;
use App\Services\WalletService;
use Illuminate\Support\Facades\Validator;

class WalletController extends BaseController
{

    public function createProvidusVirtualAccount(){
        $response = Http::withHeaders([
            'X-Auth-Signature' => env('PROVIDUS_X_AUTH_SIGNATURE'),
            'Client-Id'=>env('PROVIDUS_CLIENT_ID'),
            'content-type'=>'application/json'
        ])->post(env('PROVIDUS_BASEURL').'/PiPCreateReservedAccountNumber', [
            'account_name' => Auth::user()->first_name .' '.Auth::user()->last_name,
            'bvn'=>'www'
        ]);
        dd($response);
    }

    /**
     * @OA\Get(
     ** path="/api/v1/user/get-all-topup-requests",
     *   tags={"User"},
     *   summary="Get user all topup request",
     *   operationId="get user all topup request",
     *
     ** * @OA\Parameter(
     *      name="status",
     *      in="path",
     *      required=false,
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
     *       {"api_key": {}}
     *     }
     *
     *)
    **/
    public function getAllTopupRequests(Request $request)
    {
        $filter = strval($request->query('status'));

        $req = Auth::user()->topuprequests();
        if($filter == 'pending'){
            $req = $req->where('status', 0)->orderBy('created_at', 'desc');
        }else if($filter == 'paid'){
            $req = $req->where('status', 1)->orderBy('created_at', 'desc');
        }else{
            $req = $req->orderBy('created_at', 'desc');
        }
        return $this->successfulResponse($req->get(),'');
    }


    /**
     * @OA\Post(
     ** path="/api/v1/user/submit-topup-request",
     *   tags={"User"},
     *   summary="submit topup request",
     *   operationId="submit topup request",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"amount","document"},
     *              @OA\Property( property="reference", type="string"),
     *              @OA\Property( property="amount", type="number"),
     *              @OA\Property( property="document", type="file"),
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


    public function submitTopupRequest(Request $request){
        $this->validate($request, [
            'amount'=>'required|numeric|min:1',
            'reference'=>'nullable',
            'document' => 'required|mimes:jpeg,png,jpg,pdf|max:4048'
        ]);

        $topup = new TopupRequest;
        if($request->file('document'))
        {
            try
            {
                $newname = cloudinary()->upload($request->file('document')->getRealPath(),
                    ['folder'=>'leverpay/documents']
                )->getSecurePath();
                $topup->image_url = $newname;

            } catch (\Exception $ex) {
                return $this->sendError($ex->getMessage());
            }
        }

        $topup->user_id = Auth::id();
        $topup->amount = $request['amount'];
        if($request['reference']){
            $topup->reference = $request['reference'];
        }else{
            $topup->reference =  Str::uuid()->toString();
        }

        $topup->save();

        $user=User::where('id',Auth::id())->get(['first_name','last_name','email'])->first();
        $details=$user->first_name." ".$user->last_name." ".$user->email;
        //sent user funding request notification
        $html2 = "
            <2 style='margin-bottom: 8px'>Details</h2>
            <div style='margin-bottom: 8px'>User: {$details} </div>
            <div style='margin-bottom: 8px'>Amount: {$request['amount']} </div>
            <div style='margin-bottom: 8px'>Refrence ID: {$topup->reference} </div>
            <div style='margin-bottom: 8px'>Document: {$topup->image_url} </div>
        ";
        $to="contact@leverpay.io";

        //SmsService::sendMail("", $html2, "user funding request notification", $to);
        ZeptomailService::sendMailZeptoMail("user funding request notification", $html2, $to);

        return $this->successfulResponse([], 'Topup request submitted successfulss');
    }

    /**
     * @OA\Get(
     ** path="/api/v1/user/get-wallet",
     *   tags={"User"},
     *   summary="Get user wallet",
     *   operationId="get user  wallet details",
     *
     *
     *   @OA\Response(
     *      response=200,
     *       description="Success",
     *     ),
     *     security={
     *       {"api_key": {}}
     *     }
     *
     *)
     **/
    public function getWallet(Request $request)
    {
        // $user = User::find(1);
        // return(Auth::user());
        User::where('id', Auth::user()->id)->update([
            'zip_code' => $request->getClientIp()
        ]);
        return $this->successfulResponse(Auth::user()->wallet, '');
    }

    /**
     * @OA\Post(
     ** path="/api/v1/user/fund-wallet",
     *   tags={"User"},
     *   summary="Fund wallet",
     *   operationId="Fund wallet",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"amount"},
     *              @OA\Property( property="amount", type="number"),
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
     *       {"api_key": {}}
     *   }
     *)
     **/
    // public function fundWallet(Request $request){
    //     $this->validate($request, [
    //         'amount'=>'required|numeric'
    //     ]);

    //     $amount = $request['amount'] * 100;

    //     $user = Auth::user();
    //     //$user = User::find(1);
    //     $reference = Uuid::generate()->string;

    //     $response = Http::withHeaders([
    //         'Authorization' => 'Bearer '.env('PAYSTACK_SECRET_TEST_KEY'),
    //         'Cache-Control'=> 'no-cache',
    //         'content-type'=>'application/json'
    //     ])->post('https://api.paystack.co/transaction/initialize', [
    //         'email' => $user->email,
    //         'amount' => $amount,
    //         'reference'=>$reference,
    //         'callback_url'=>"https://ad2a-105-112-190-126.ngrok-free.app/api/v1/verify-transaction"
    //     ]);

    //     if(!$response['status']){
    //         abort(400, $response['message']);
    //     }

    //     $trans = new Transaction();
    //     $trans->user_id = $user->id;
    //     $trans->amount = $request['amount'];
    //     $trans->reference_no = $reference;
    //     $trans->type = 'credit';
    //     $trans->save();

    //     return $this->successfulResponse(['authorization_url'=> $response['data']['authorization_url']], '');
    // }

    /**
     * @OA\Get(
     ** path="/api/v1/verify-transaction",
     *   tags={"Authentication & Verification"},
     *   summary="Verify transaction",
     *   operationId="Verify wallet transaction",
     *
     *   @OA\Parameter(
     *      name="trxref",
     *      in="path",
     *      required=true,
     *      @OA\Schema(
     *           type="string"
     *      )
     *   ),
     *
     *  @OA\Parameter(
     *      name="reference",
     *      in="path",
     *      required=true,
     *      @OA\Schema(
     *           type="string"
     *      )
     *   ),
     *
     *   @OA\Response(
     *      response=200,
     *       description="Success",
     *     ),
     *     security={
     *       {"api_key": {}}
     *     }
     *
     *)
     **/
    public function verifyTransaction(Request $request){
        $trxref = strval($request->query('trxref'));
        $ref = strval($request->query('reference'));

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.env('PAYSTACK_SECRET_TEST_KEY'),
            'Cache-Control'=> 'no-cache',
            'content-type'=>'application/json'
        ])->get('https://api.paystack.co/transaction/verify/'.$ref);

        if(!$response['status']){
            abort(400, $response['message']);
        }

        if($response['data']['status'] != 'success'){
            abort(400, "Pending Payment");
        }

        $trans = Transaction::where('reference_no', $ref)->first();
        if(!$trans){
            abort(400, "Invalid transaction reference number");
        }

        if($trans->status != 0){
            abort(400, "Transaction has already been verified");
        }

        DB::transaction( function() use($trans, $response, $trxref) {
            $trans->tnx_reference_no = $trxref;
            $trans->status = 1;
            $trans->extra = json_encode($response['data']);
            $trans->balance =  $trans->user->wallet ? floatval($trans->user->wallet->amount) + floatval($trans->amount) : floatval($trans->amount);
            $trans->save();

            WalletService::addToWallet($trans->user_id,$trans->amount);
        });
        return $this->successfulResponse([],"Payment successful");
    }

    /**
     * @OA\Get(
     ** path="/api/v1/user/get-user-transactions",
     *   tags={"User"},
     *   summary="Get user transactions",
     *   operationId="get user transactions details",
     *
     *
     *   @OA\Response(
     *      response=200,
     *       description="Success",
     *     ),
     *     security={
     *       {"api_key": {}}
     *     }
     *
     *)
     **/
    public function getUserTransaction()
    {
        $userId=Auth::user()->id;
        $transactions=Transaction::join('users', 'users.id', '=', 'transactions.user_id')
            ->where('transactions.user_id', $userId)
            ->orderBy('transactions.created_at', 'DESC')
            ->get([
                'transactions.reference_no',
                'transactions.merchant',
                'transactions.tnx_reference_no',
                'transactions.amount',
                'transactions.currency',
                'transactions.transaction_details',
                'transactions.balance',
                'transactions.status',
                'transactions.type',
                'transactions.extra AS other_details',
                'transactions.created_at',
                'users.email'
            ]);
        $transactions->transform(function ($transaction)
        {
            $details=json_decode($transaction->transaction_details);

            if(!empty($details->invoice_uuid))
            {
                $invoice_uuid=$details->invoice_uuid;

                $transaction->transaction_details = Invoice::query()->where('uuid',$invoice_uuid)->with(['merchant' => function ($query) {
                    $query->select('id','uuid', 'first_name','last_name','phone','email')->with('merchant');
                }])->with(['user' => function ($query) {
                    $query->select('id','uuid', 'first_name','last_name','phone','email');
                }])->first();
            }
            else if(!empty($details->transfer_uuid))
            {
                $transfer_uuid=$details->transfer_uuid;

                $transaction->transaction_details = Transfer::query()->where('uuid',$transfer_uuid)->with(['sender' => function ($query) {
                    $query->select('id','uuid', 'first_name','last_name','phone','email');
                }])->with(['recipient' => function ($query) {
                    $query->select('id','uuid', 'first_name','last_name','phone','email');
                }])->first();

            }
            else{
                $transaction->transaction_details = $details;
            }

            return $transaction;
        });

        return $this->successfulResponse($transactions, 'User transactions successfully retrieved');
    }



    /**
     * @OA\Post(
     ** path="/api/v1/user/transfer",
     *   tags={"User"},
     *   summary="transfer",
     *   operationId="transfer",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              @OA\Property( property="email", type="string"),
     *              @OA\Property( property="amount", type="number"),
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

    public function transfer(Request $request)
    {
        $data2['activity']="Transfer Attempt - User: " . Auth::user()->id .'-'.Auth::user()->first_name. ' '. Auth::user()->first_name. ' Amount:'. $request['amount'];
        $data2['user_id']=Auth::user()->id;

        ActivityLog::createActivity($data2);
        $this->validate($request, [
            'email'=>'required|email',
            'amount'=>'required|numeric|min:1000'
        ]);

        $user= Auth::user();

        User::where('id', $user->id)->update([
            'zip_code' => $request->getClientIp()
        ]);



        $trans = User::where('email', $request['email'])->first();
        if(!$trans){
            return $this->sendError('Recipient account not found',[],404);
        }

        if($user->wallet->withdrawable_amount < $request['amount']){
            return $this->sendError("Insufficient balance",[],400);
        }



        if($request['email'] == $user->email){
            return $this->sendError("Invalid transfer, you can't transfer to yourself",[],400);
        }

        $otp = rand(1000, 9999);

        $transfer = new Transfer;
        $transfer->user_id = $user->id;
        $transfer->receiver_id = $trans->id;
        $transfer->amount = $request['amount'];
        $transfer->otp = $otp;
        $transfer->save();

        $content = "A request to transfer {$request['amount']} has been made on your account, to verify your otp is: <br /> {$otp}";

        // Mail::to($user->email)->send(new GeneralMail($content, 'OTP'));
        SmsService::sendSms("Dear {$user->first_name}, A request to transfer {$request['amount']} has been made on your account, to verify your One-time Confirmation code is {$otp} and it will expire in 10 minutes. Please do not share For enquiry: contact@leverpay.io", '234'.$user->phone);

        // ZeptomailService::sendMailZeptoMail("LeverPay Transfer OTP " ,"Dear {$user->first_name}, ".$content, $user->email);
        $msg = [
            'name' => $user->first_name,
            'otp' => $otp,
            'amount'=>$request['amount']
        ];
        ZeptomailService::sendTemplateZeptoMail("LeverPay Transfer OTP " ,"Dear {$user->first_name}, ".$content, $user->email);

        $data2['activity']="You submitted a request to transfer {$request['amount']}";
        $data2['user_id']=$user->id;

        ActivityLog::createActivity($data2);

        return $this->successfulResponse($transfer, 'OTP sent');
    }

    public function verifyTransfer(Request $request){
        $this->validate($request, [
            'otp'=>'required|numeric',
            'uuid'=>'required|string'
        ]);

        $user= Auth::user();
        $trans = Transfer::where('uuid', $request['uuid'])->where('status', 0)->first();

        if(!$trans){
            return $this->sendError("Transfer request not found",[],400);
        }

        if($request['otp'] != $trans->otp){
            return $this->sendError("Invalid otp, please try again",[],400);
        }

        if($trans->user_id != $user->id){
            return $this->sendError("Invalid request",[],400);
        }

        // return $trans->recipient->wallet;

        if($user->wallet->withdrawable_amount < $trans['amount']){
            return $this->sendError("Insufficient balance",[],400);
        }

        DB::transaction( function() use($trans, $user) {
            $ext = 'LP_'.Uuid::generate()->string;
            $transaction = new Transaction();
            $transaction->user_id = $user->id;
            $transaction->reference_no	= 'LP_'.Uuid::generate()->string;
            $transaction->tnx_reference_no	= $ext;
            $transaction->amount =$trans['amount'];
            $transaction->balance = floatval($user->wallet->withdrawable_amount) - floatval($trans['amount']);
            $transaction->type = 'debit';
            $transaction->merchant = 'transfer';
            $transaction->status = 1;

            $details = [
                "transfer_uuid"=>$trans['uuid']
            ];
            $transaction->transaction_details = json_encode($details);
            $transaction->save();

            $transaction2 = new Transaction();
            $transaction2->user_id = $trans->receiver_id;
            $transaction2->reference_no	= $ext;
            $transaction2->tnx_reference_no	= Uuid::generate()->string;
            $transaction2->amount =$trans['amount'];
            $transaction2->balance = floatval($trans->recipient->wallet->withdrawable_amount) + floatval($trans['amount']);
            $transaction2->type = 'credit';
            $transaction2->merchant = 'transfer';
            $transaction2->status = 1;
            $details2 = [
                "transfer_uuid"=>$trans['uuid']
            ];
            $transaction2->transaction_details = json_encode($details2);
            $transaction2->save();



            WalletService::addToWallet($trans->receiver_id, $trans['amount']);
            WalletService::subtractFromWallet($user->id, $trans['amount']);

            $trans->status = 0;
            $trans->otp = rand(1000, 9999);
            $trans->save();
        });

        $trans->status = 1;
        $trans->save();

        $data2['activity']="Transfer of {$trans['amount']} is successful";
        $data2['user_id']=$user->id;
        ActivityLog::createActivity($data2);

        $content = "Transfer of {$request['amount']} is successful";
        SmsService::sendMail("Dear {$user->first_name},", $content, "Transfer Successful", $user->email);

        $content = "You have received {$request['amount']} from {$user->first_name} {$user->last_name}";
        //SmsService::sendMail("Dear {$trans->recipient->first_name},", $content, "Wallet Credit", $trans->receiver_id);
        ZeptomailService::sendMailZeptoMail("Wallet Credit", "Dear {$trans->recipient->first_name}, ".$content, $trans->receiver_id);

        return $this->successfulResponse([], 'Transfer successful');
    }

    //it has been already done in admin controller

    public function getAccountNos()
    {
        $acc = DB::table('lever_pay_account_no')->get();
        return $this->successfulResponse($acc, '');
    }

    public function generateAccount(Request $request){
        $this->validate($request,[
            'type'=> 'required|string',
            'amount'=>'nullable|numeric|min:1'
        ]);
        $providus = ProvidusService::generateDynamicAccount(Auth::user()->first_name.' '. Auth::user()->last_name);
        $account = new Account();
        $account->user_id = Auth::user()->id;
        $account->bank = 'providus';
        $account->amount = $request->amount;
        $account->accountNumber = $providus->account_number;
        $account->accountName = $providus->account_name;
        if($request->type == 'topup'){
            $account->type = 'topup';
        }else if($request->type == 'merchant'){
            $account->type = 'merchant';
        }else if($request->type == 'checkout'){
            $account->type = 'checkout';
        } else{
            $account->type = 'other';
        }
        $account->save();

        return $this->successfulResponse($account,'Account generated successfully');
    }

    /**
     * @OA\Get(
     ** path="/api/v1/merchant/get-merchant-wallet",
     *   tags={"Merchant"},
     *   summary="Get merchant wallet balance",
     *   operationId="get merchant  wallet balance",
     *
     *
     *   @OA\Response(
     *      response=200,
     *       description="Success",
     *     ),
     *     security={
     *       {"api_key": {}}
     *     }
     *
     *)
     **/
    public function getMerchantWallet()
    {
        // $user = User::find(1);
        // return(Auth::user());
        return $this->successfulResponse(Auth::user()->wallet, '');
    }
}
