<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\BaseController;
use App\Models\Card;
use App\Models\CardPayment;
use App\Models\Transaction;
use App\Services\CardService;
use App\Services\SmsService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Uid\Ulid;
use Webpatser\Uuid\Uuid;

class CheckoutController extends BaseController
{
    public function createPayment(Request $request) {
        $this->validate($request, [
            'amount' => 'required',
            'merchant_reference' => 'required',
            'pan',
            'cvv',
            'expiry'
        ]);

        $user = Auth::user();

        $walletBalance = Auth::user()->wallet->withdrawable_amount;

        if ($walletBalance < $request->get('amount')) {
            $this->sendError('Insufficient balance to create payment');
        }

        if (!(new CardService)->validateCredentials(
            $request->get('pan'),
            $request->get('cvv'),
            $request->get('pin'),
            $request->get('expiry'),
        )) {
            $this->sendError('Card details incorrect');
        }

        $card = Card::where('user_id',Auth::id())
            ->where('card_number', $request->get('pan'))
            ->first();

        $cardPayment = new CardPayment();
        $cardPayment->uuid = Uuid::generate(4)->string;
        $cardPayment->amount = $request->get('amount');
        $cardPayment->card_id = $card->id;
        $cardPayment->merchant_reference = $request->get('merchant_reference');
        $cardPayment->payment_reference = 'LP_'.Ulid::generate();
        $cardPayment->otp = random_int(1000,9999);
        $cardPayment->save();

        $content = "A card transaction with value {$request['amount']} has been initiated on your account, to verify your otp is: <br /> {$cardPayment->otp}";

        SmsService::sendSms("Dear {$user->first_name},A card transaction with value {$request['amount']} has been initiated on your account, to verify your One-time Confirmation code is {$cardPayment->otp} and it will expire in 10 minutes. Please do not share For enquiry: contact@leverpay.io", '234'.$user->phone);
        SmsService::sendMail("Dear {$user->first_name},", $content, "LeverPay Card Transaction OTP", $user->email);

        $this->successfulResponse([
            'transaction_id' => $cardPayment->id,
            'amount' => $request->get('amount')
        ], 'Payment created');

    }


    public function completePayment(Request $request) {
        $this->validate($request, [
            'otp' => 'required|size:4',
            'payment_id' => 'required'
        ]);

        $cardPayment = CardPayment::where('uuid', $request->get('payment_id'))->firstOrFail();

        if ($cardPayment->otp !== (integer)$request->get('otp')) {
            $cardPayment->retries_left -= 1;
            $cardPayment->save();

            if ($cardPayment->retries_left == 0) {
                $cardPayment->status = 'CANCELED';
                $cardPayment->save();
                return $this->sendError('Multiple incorrect OTP, transaction cancelled');
            }
            return $this->sendError('Incorrect OTP. '. $cardPayment->retries_left . " retries left");
        }


        $user = Auth::user();

        DB::transaction( function() use($user, $cardPayment) {
            $transaction = new Transaction();
            $transaction->user_id = $user->id;
            $transaction->reference_no = 'LP_' . Uuid::generate()->string;
            $transaction->tnx_reference_no = $cardPayment->payment_reference;
            $transaction->amount = $cardPayment->amount;
            $transaction->balance = floatval($user->wallet->withdrawable_amount) - floatval($cardPayment->amount);
            $transaction->type = 'debit';
            $transaction->merchant = 'card_payment';
            $transaction->status = 1;
            $transaction->save();

            WalletService::subtractFromWallet($user->id, $cardPayment->amount, 'naira');

            $cardPayment->status = 'SUCCESSFUL';
            $cardPayment->save();

        });

        return $this->successfulResponse([], "Card payment successful");
    }
}
