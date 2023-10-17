<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\BaseController;
use App\Models\Transaction;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Webpatser\Uuid\Uuid;

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
        $user = Auth::user();
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

        $otp = random_int(1000,9999);

        $content = "A card transaction with value {$request['amount']} has been initiated on your account, to verify your otp is: <br /> {$otp}";

        SmsService::sendSms("Dear {$user->first_name},A card transaction with value {$request['amount']} has been initiated on your account, to verify your One-time Confirmation code is {$otp} and it will expire in 10 minutes. Please do not share For enquiry: contact@leverpay.io", '234'.$user->phone);
        SmsService::sendMail("Dear {$user->first_name},", $content, "LeverPay Card Transaction OTP", $user->email);
    }

    public function completeTransaction(Request $request) {
        $this->validate($request, [
            'otp' => 'required|size:4',
            'transaction_hash' => 'required'
        ]);


        $user = Auth::user();

        $transaction = [];

        if ($request->get('otp') !== $transaction['otp']) {
            return $this->sendError("Incorrect OTP.");
        }

        DB::transaction( function() use($transaction, $user) {
            $ext = 'LP_' . Uuid::generate()->string;
            $transaction = new Transaction();
            $transaction->user_id = $user->id;
            $transaction->reference_no = 'LP_' . Uuid::generate()->string;
            $transaction->tnx_reference_no = $ext;
            $transaction->amount = $transaction['amount'];
            $transaction->balance = floatval($user->wallet->withdrawable_amount) - floatval($transaction['amount']);
            $transaction->type = 'debit';
            $transaction->merchant = 'transfer';
            $transaction->status = 1;
            $transaction->save();
        });
    }
}
