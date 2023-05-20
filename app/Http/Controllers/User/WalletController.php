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
     ** path="/api/v1/user/verify-transaction",
     *   tags={"User"},
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
}
