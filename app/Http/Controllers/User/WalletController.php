<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\BaseController;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Webpatser\Uuid\Uuid;
use App\Services\WalletService;

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
    public function getWallet()
    {
        // $user = User::find(1);
        // return(Auth::user());
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
    public function fundWallet(Request $request){
        $this->validate($request, [
            'amount'=>'required|numeric'
        ]);

        $amount = $request['amount'] * 100;

        $user = Auth::user();
        //$user = User::find(1);
        $reference = Uuid::generate()->string;

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.env('PAYSTACK_SECRET_TEST_KEY'),
            'Cache-Control'=> 'no-cache',
            'content-type'=>'application/json'
        ])->post('https://api.paystack.co/transaction/initialize', [
            'email' => $user->email,
            'amount' => $amount,
            'reference'=>$reference,
            'callback_url'=>"https://ad2a-105-112-190-126.ngrok-free.app/api/v1/verify-transaction"
        ]);

        if(!$response['status']){
            abort(400, $response['message']);
        }

        $trans = new Transaction();
        $trans->user_id = $user->id;
        $trans->amount = $request['amount'];
        $trans->reference_no = $reference;
        $trans->type = 'credit';
        $trans->save();

        return $this->successfulResponse(['authorization_url'=> $response['data']['authorization_url']], '');
    }

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
        $transaction=Transaction::where('user_id', $userId)
            ->orderBy('created_at', 'DESC')
            ->get([
                'reference_no',
                'tnx_reference_no',
                'amount',
                'transaction_details',
                'balance',
                'status',
                'type',
                'extra AS other_details',
                'created_at'
            ]);

        return $this->successfulResponse($transaction, 'User transactions successfully retrieved');
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

    public function transfer(Request $request){
        $this->validate($request, [
            'email'=>'required|email',
            'amount'=>'required|numeric'
        ]);

        $user= Auth::user();

        if($user->wallet->withdrawable_amount < $request['amount']){
            return $this->sendError("Insufficient balance",[],400);
        }

        $trans = User::where('email', $request['email'])->first();
        if(!$trans){
            return $this->sendError('Recipient account not found',[],404);
        }

        if($request['email'] == $user->email){
            return $this->sendError("Invalid transfer, you can't transfer to yourself",[],400);
        }

        DB::transaction( function() use($trans, $user, $request) {
            $ext = 'LP_'.Uuid::generate()->string;
            $transaction = new Transaction();
            $transaction->user_id = $user->id;
            $transaction->reference_no	= 'LP_'.Uuid::generate()->string;
            $transaction->tnx_reference_no	= $ext;
            $transaction->amount =$request['amount'];
            $transaction->balance = floatval($user->wallet->withdrawable_amount) - floatval($request['amount']);
            $transaction->type = 'debit';
            $transaction->merchant = 'transfer';

            $details = [
                'receiver'=>[
                    "uuid"=>$trans->uuid,
                    "first_name"=>$trans->first_name,
                    "last_name"=>$trans->last_name,
                    "email"=>$trans->email
                ]
            ];
            $transaction->transaction_details = json_encode($details);
            $transaction->save();

            $transaction2 = new Transaction();
            $transaction2->user_id = $trans->id;
            $transaction2->reference_no	= $ext;
            $transaction2->tnx_reference_no	= Uuid::generate()->string;
            $transaction2->amount =$request['amount'];
            $transaction2->balance = floatval($trans->wallet->withdrawable_amount) + floatval($request['amount']);
            $transaction2->type = 'credit';
            $transaction2->merchant = 'transfer';
            $details2 = [
                'sender'=>[
                    "uuid"=>$user->uuid,
                    "first_name"=>$user->first_name,
                    "last_name"=>$user->last_name,
                    "email"=>$user->email
                ],
            ];
            $transaction2->transaction_details = json_encode($details2);
            $transaction2->save();

            WalletService::addToWallet($trans->id, $request['amount']);
            WalletService::subtractFromWallet($user->id, $request['amount']);
        });

        return $this->successfulResponse([], 'Transfer successful');
    }
}
