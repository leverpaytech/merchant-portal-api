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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Uid\Ulid;
use Webpatser\Uuid\Uuid;

class CheckoutController extends BaseController
{

    /**
     * @OA\Post(
     ** path="/api/v1/user/checkout/create-payment",
     *   tags={"User"},
     *   summary="Create Card Payment",
     *   operationId="Create Card Payment",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"pin"},
     *              @OA\Property( property="pin", type="number"),
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
    public function createPayment(Request $request) {
        $this->validate($request, [
            'amount' => 'required',
            'merchant_reference' => 'required',
            'pan',
            'cvv',
            'expiry',
            'customer_information' => 'required',
            'customer_information.email' => 'required',
            'customer_information.first_name' => 'required',
            'customer_information.last_name' => 'required',
            'customer_information.phone_number' => 'required',
        ]);

        if (!(new CardService)->validateCredentials(
            $request->get('pan'),
            $request->get('cvv'),
            $request->get('pin'),
            $request->get('expiry'),
        )) {
            $this->sendError('Card details incorrect');
        }

        $card = Card::where('card_number', $request->get('pan'))
            ->first();

        $user = $card->user;

        $walletBalance = $user->wallet->withdrawable_amount;

        if ($walletBalance < $request->get('amount')) {
            $this->sendError('Insufficient balance to create payment');
        }
        $otp = random_int(1000,9999);
        $cardPayment = new CardPayment();
        $cardPayment->uuid = Uuid::generate(4)->string;
        $cardPayment->amount = $request->get('amount');
        $cardPayment->card_id = $card->id;
        $cardPayment->merchant_reference = $request->get('merchant_reference');
        $cardPayment->payment_reference = 'LP_'.Ulid::generate();
        $cardPayment->otp = Hash::make($otp);
        $cardPayment->status = 'PENDING';
        $cardPayment->customer_information = json_encode($request->get('customer_information'));
        $cardPayment->save();

        $content = "A card transaction with value {$request['amount']} has been initiated on your account, to verify your otp is: <br /> {$otp}";

        SmsService::sendSms("Dear {$user->first_name},A card transaction with value {$request['amount']} has been initiated on your account, to verify your One-time Confirmation code is {$otp} and it will expire in 10 minutes. Please do not share For enquiry: contact@leverpay.io", '234'.$user->phone);
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

        if (!Hash::check($request->get('otp'), $cardPayment->otp)) {
            $cardPayment->retries_left -= 1;
            $cardPayment->save();

            if ($cardPayment->retries_left == 0) {
                $cardPayment->status = 'CANCELED';
                $cardPayment->save();
                return $this->sendError('Multiple incorrect OTP, transaction cancelled');
            }
            return $this->sendError('Incorrect OTP. '. $cardPayment->retries_left . " retries left");
        }


        $user = $cardPayment->card->user;

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
