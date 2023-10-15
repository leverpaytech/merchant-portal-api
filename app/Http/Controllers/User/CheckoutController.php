<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;

class CheckoutController extends BaseController
{
    public function createPayment(Request $request) {
        $this->validate($request, [
            'amount' => 'required',
            'merchant_reference' => 'required'
        ]);

        $walletBalance = Auth::user()->wallet->withdrawable_amount;

        if ($walletBalance < $request->get('amount')) {
            $this->sendError('Insufficient balance to create payment');
        }

        [$transactionHash, $transactionReference]= (function ($amount, $reference) {

        })($request->get('amount'), $request->get('merchant_reference'));

        $this->successfulResponse([
            'hash' => $transactionHash,
            'transaction_reference' => $transactionReference,
            'amount' => $request->get('amount')
        ], 'Payment created');

    }
    public function checkout(Request $request) {
        $this->validate($request, [
            'pan' => 'required',
            'cvv' => 'required',
            'pin' => 'required'
        ]);

        // Get transaction with hash

        $transactionAmount = 0.00;
        $walletBalance = Auth::user()->wallet->withdrawable_amount;

        if ($transactionAmount > $walletBalance) {
            $this->sendError('Insufficient balance');
        }
    }
}
