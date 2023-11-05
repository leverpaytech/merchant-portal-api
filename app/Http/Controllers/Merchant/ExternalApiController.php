<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Models\Checkout;
use App\Models\MerchantKeys;
use Illuminate\Http\Request;

class ExternalApiController extends Controller
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

    public function initialize(Request $request){
        $this->validate($request, [
            'amount'=> 'required|numeric|min:1',
            'reference'=> "nullable",
            'currency'=>'nullable|string',
            'product'=>'nullable|string',
        ]);

        $currency = $request->currency;
        if($request->currency && ($request->currency != 'dollar' || $request->currency != 'naira')) {
            abort(400, 'Invalid currency, only naira or dollar is accepted');
        }

        if(!$request->currency || empty($request->currency)) {
            $currency = 'naira';
        }
        $checkout = new Checkout();
        $checkout->merchant_id = $this->merchant->user_id;
        $checkout->amount = $request->amount;
        $checkout->currency = $currency;
        $checkout->email = $request->email;
        $checkout->product = $request->product;

    }
}
