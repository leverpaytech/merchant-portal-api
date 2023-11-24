<?php

namespace App\Http\Controllers\External;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Checkout;
use App\Models\ExchangeRate;
use App\Models\MerchantKeys;
use Illuminate\Support\Str;
use DB;
use App\Http\Controllers\BaseController;

class MerchantController extends BaseController
{
    private $merchant;

    public function __construct(Request $request)
    {
        $token = $request->bearerToken();
        $this->merchant = MerchantKeys::where('live_secret_key', $token)->first();
        if(!$this->merchant) {
            abort(400, 'Unauthorized request, check token and try again');
        }
    }

    /**
     * @OA\Post(
     ** path="/api/v1/leverchain/transaction/initialize",
     *   tags={"ExternalApi"},
     *   summary="Initialize Transaction",
     *   operationId="Initialize Transaction",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"amount"},
     *              @OA\Property( property="amount", type="string"),
     *              @OA\Property( property="merchant_reference", type="string"),
     *              @OA\Property( property="currency", type="string"),
     *              @OA\Property( property="product", type="string")
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
     *   },
     *
     *)
     **/
    public function initialize(Request $request){
        $this->validate($request, [
            'amount'=> 'required|numeric|min:1',
            'merchant_reference'=> "nullable",
            'currency'=>'nullable|string',
            'product'=>'nullable|string',
        ]);

        $currency = $request->currency;
        if($request->currency && ($request->currency != 'dollar' || $request->currency != 'naira')) {
            // abort(400, 'Invalid currency, only naira or dollar is accepted');
            return $this->sendError('Invalid currency, only naira or dollar is accepted',[],400);
        }

        $check = Checkout::where('merchant_reference',$request->merchant_reference)->where('merchant_id', $this->merchant->user_id)->first();
        if($check){
            return $this->sendError('Merchant reference already exit',[],400);
        }

        if(!$request->currency || empty($request->currency)) {
            $currency = 'naira';
        }

        $ref = $request->merchant_reference;
        if(!$ref){
            $ref = strtolower(Str::random(30));
        }

        $rates = ExchangeRate::latest()->first();
        // $vat = $request->amount * ($rates->vat / 100);

        $fee_percent = $currency == 'naira' ? $rates->local_transaction_rate : $rates->international_transaction_rate;
        $fee = floatval($request->amount) * ($fee_percent / 100);

        $code = Str::random(12);
        $check = DB::table('checkouts')->where('access_code', $code)->first();
        if($check){
            $code = Str::random(24);
        }

        $checkout = new Checkout();
        $checkout->merchant_id = $this->merchant->user_id;
        $checkout->amount = $request->amount;
        $checkout->fee = $fee;
        $checkout->total = floatval($request->amount) + $fee;
        $checkout->currency = $currency;
        $checkout->email = $request->email;
        $checkout->product = $request->product;
        $checkout->merchant_reference = $ref;
        $checkout->access_code = $code;
        $checkout->authorization_url = env('CHECKOUT_BASE_URL').'/'.$code;
        $checkout->save();

        return $this->successfulResponse($checkout);

    }
}
