<?php

namespace App\Http\Controllers\External;

use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use App\Models\Checkout;
use App\Services\ProvidusService;
use App\Models\Account;
use App\Models\Card;
use App\Models\CardPayment;
use App\Models\Transaction;
use Symfony\Component\Uid\Ulid;
use Illuminate\Support\Facades\Hash;
use Webpatser\Uuid\Uuid;
use App\Services\SmsService;
use App\Services\ZeptomailService;
use Illuminate\Support\Facades\DB;
use App\Services\WalletService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CheckoutController extends BaseController
{
    public function verifyRequest($access_code){
        $checkout = Checkout::where('access_code', strval($access_code))->first();
        if(!$checkout) {
            return $this->sendError('Invalid access code',[],400);
        }
        return $this->successfulResponse([...$checkout->toArray(), 'merchant'=>$checkout->merchant->merchant->toArray()]);
    }

    public function saveDetails(Request $request){
        $this->validate($request, [
            'first_name'=> "required|string",
            'last_name'=> "required|string",
            'email'=> "required|string",
            'phone'=> "required|string",
            'access_code'=> "required|string",
        ]);

        $checkout = Checkout::where('access_code', $request->access_code)->first();
        if(!$checkout) {
            return $this->sendError('Invalid access code',[],400);
        }

        if($checkout->status != 'PENDING') {
            return $this->sendError('This transaction has already been processed',[],400);
        }

        $checkout->first_name = $request->first_name;
        $checkout->last_name = $request->last_name;
        $checkout->email = $request->email;
        $checkout->phone = $request->phone;
        $checkout->save();

        return $this->successfulResponse($checkout, 'Details saved successfully');
    }

    public function payWithTransfer(Request $request){
        $this->validate($request, [
            'access_code' => 'required|string',
        ]);
        $checkout = Checkout::where('access_code', $request->access_code)->first();
        if(!$checkout) {
            return $this->sendError('Invalid access code',[],400);
        }

        if($checkout->currency != 'naira') {
            return $this->sendError('Dollar transaction can only be paid with card',[],400);
        }

        $providus = ProvidusService::generateDynamicAccount('LeverPay-'.$checkout->first_name.' '. $checkout->last_name);
        $account = new Account();
        $account->user_id = $checkout->merchant_id;
        $account->bank = 'providus';
        $account->amount = $checkout->amount;
        $account->accountNumber = $providus->account_number;
        $account->accountName = $providus->account_name;
        $account->type = 'checkout';
        $account->model_id = $checkout->uuid;
        $account->save();

        return $this->successfulResponse($account,'Account generated successfully');
    }

    public function payWithCard(Request $request){
        $this->validate($request, [
            'card_number' => 'required|numeric',
            'cvv'=>'required|numeric',
            'access_code'=>'required|string',
            'expiry'=>'required|string'
        ]);

        $checkout = Checkout::where('access_code', $request->access_code)->first();
        if(!$checkout) {
            return $this->sendError('Invalid access code',[],400);
            // abort(400, 'Invalid card details');
        }

        if($checkout->status == 'CANCELLED'){
            return $this->sendError('This transaction has been cancelled',[],400);
        }
        if($checkout->status == 'SUCCESSFUL'){
            return $this->sendError('This transaction has been processed',[],400);
        }

        $card = Card::where('card_number',$request['card_number'])->where('cvv', $request['cvv'])->where('expiry', $request['expiry'])->first();
        if(!$card) {
            return $this->sendError('Invalid card details',[],400);
            // abort(400, 'Invalid card details');
        }

        $user = $card->user;
        if($checkout->currency == 'naira'){
            if ($user->wallet->withdrawable_amount < $checkout->total) {
                return $this->sendError('Insufficient naira balance',[],400);
                // abort(400, 'Insufficient naira balance');
            }
        }else{
            if ($user->wallet->dollar < $checkout->total) {
                return $this->sendError('Insufficient dollar balance',[],400);
                // abort(400, 'Insufficient dollar balance');
            }
        }
        try{
            DB::beginTransaction();
            $uuid =  Uuid::generate(4)->string;
            $otp = random_int(1000,9999);
            $cardPayment = new CardPayment();
            $cardPayment->uuid =$uuid;
            $cardPayment->amount = $checkout->total;
            $cardPayment->card_id = $card->id;
            $cardPayment->merchant_reference = $checkout->merchant_reference;
            $cardPayment->payment_reference = 'LP_'.Ulid::generate();
            $cardPayment->otp = Hash::make($otp);
            $cardPayment->status = 'PENDING';
            $cardPayment->customer_information = json_encode([
                'first_name'=>$checkout->first_name,
                'last_name'=>$checkout->last_name,
                'phone'=>$checkout->phone,
                'email'=>$checkout->email,
                'user_id'=>$card->user->uuid
            ]);
            $cardPayment->order_details = json_encode([
                'product'=>$checkout->product,
                'amount'=>$checkout->amount,
                'total'=>$checkout->total,
                'currency'=>$checkout->currency,
                'fee'=>$checkout->fee,
                'vat'=>$checkout->vat,
            ]);
            $cardPayment->card_paymentable_type = get_class($checkout);
            $cardPayment->card_paymentable_id = $checkout->id;
            $cardPayment->save();

            $content = "Hi {$user->first_name}, <br /> A card transaction with value {$checkout['total']} has been initiated on your account, to verify your otp is: <br /> {$otp}";

            ZeptomailService::sendMailZeptoMail("LeverPay Card Transaction OTP", $content,  $user->email);
            SmsService::sendSms("Dear {$user->first_name},A card transaction with value {$checkout['total']} has been initiated on your account, to verify your One-time Confirmation code is {$otp} and it will expire in 10 minutes. Please do not share For enquiry: contact@leverpay.io", '234'.$user->phone);

            DB::commit();
            return $this->successfulResponse([
                'payment_id' => $uuid,
            ], 'OTP sent successfully');
        }catch(\Exception $e){
            DB::rollBack();
            return $this->sendError($e->getMessage(),[],400);
        }
    }

    public function verifyCardOTP(Request $request){
        $this->validate($request, [
            'payment_id' => 'required|string',
            'otp' => 'required'
        ]);

        $cardPayment = CardPayment::where('uuid', $request->payment_id)->first();
        if(!$cardPayment){
            return $this->sendError('Card payment not found',[],400);
        }
        if($cardPayment->status == 'CANCELLED'){
            return $this->sendError('Transaction has been cancelled',[],400);
        }
        if($cardPayment->status == 'SUCCESSFUL'){
            return $this->sendError('Transaction has been completed',[],400);
        }

        try{
            DB::beginTransaction();

            if (!Hash::check($request->get('otp'), $cardPayment->otp)) {
                $cardPayment->retries_left -= 1;
                $cardPayment->save();

                if ($cardPayment->retries_left == 0) {
                    $cardPayment->status = 'CANCELLED';
                    $cardPayment->save();
                    $cardPayment->card_paymentable->update([
                        'status' => 'CANCELLED',
                    ]);
                    DB::commit();
                    return $this->sendError('Multiple incorrect OTP, transaction cancelled',[], 400);
                }
                DB::commit();
                return $this->sendError('Incorrect OTP. '. $cardPayment->retries_left . " retries left", [],400);
                // abort(400, 'Incorrect OTP. '. $cardPayment->retries_left . " retries left");
            }else{
                if(Carbon::now() > Carbon::parse($cardPayment->created_at)->addMinutes(15)){
                    return $this->sendError('Token has expiry',[], 400);
                }
            }

            $user = $cardPayment->card->user;

            $transaction = new Transaction();
            $transaction->user_id = $user->id;
            $transaction->reference_no = 'LP_' . Uuid::generate(4)->string;
            $transaction->tnx_reference_no = $cardPayment->payment_reference;
            $transaction->amount = $cardPayment->amount;
            $transaction->balance = floatval($user->wallet->withdrawable_amount) - floatval($cardPayment->amount);
            $transaction->type = 'debit';
            $transaction->merchant = 'card';
            $transaction->transaction_details = json_encode([...$cardPayment->card_paymentable->toArray(),'business_name'=>$cardPayment->card_paymentable->merchant->merchant->business_name ]);

            $transaction->status = 1;
            $transaction->save();

            $transaction = new Transaction();
            $transaction->user_id = $cardPayment->card_paymentable->merchant_id;
            $transaction->reference_no = $cardPayment->payment_reference;
            $transaction->tnx_reference_no = 'LP_' . Uuid::generate(4)->string;
            $transaction->amount = $cardPayment->card_paymentable->amount;
            $transaction->balance = floatval($cardPayment->card_paymentable->merchant->wallet->withdrawable_amount) + floatval($cardPayment->card_paymentable->amount);
            $transaction->type = 'credit';
            $transaction->merchant = 'card';
            $transaction->status = 1;
            $transaction->transaction_details = json_encode($cardPayment);
            $transaction->extra = json_encode($cardPayment->card_paymentable);
            $transaction->save();

            WalletService::subtractFromWallet($user->id, $cardPayment->amount, 'naira');
            WalletService::addToWallet($cardPayment->card_paymentable->merchant_id, $cardPayment->card_paymentable->amount, 'naira');

            $html="<p style='margin-bottom: 8px'>Dear {$user->first_name},</p><p style='margin-bottom: 10px'>$cardPayment->amount has been debited via your card</p><p> Best regards, </p><p> Leverpay </p>";

            $html2 = "<p style='margin-bottom: 8px'> Dear {$cardPayment->card_paymentable->merchant->merchant->business_name}, <br />
                    A payment of {$cardPayment->card_paymentable->amount} has been paid into your wallet.

                </p>
                <p style='margin-bottom: 2px'> Product: {$cardPayment->card_paymentable->product}</p>
                <p style='margin-bottom: 2px'> Access Code:  {$cardPayment->card_paymentable->access_code}</p>
                <p style='margin-bottom: 2px'> Mechant reference:  {$cardPayment->card_paymentable->merchant_reference}</p>
                <p style='margin-bottom: 2px'> Transaction reference:  {$cardPayment->payment_reference}</p>
                <p style='margin-bottom: 2px'> Customer Name:  {$cardPayment->card_paymentable->first_name} {$cardPayment->card_paymentable->last_name}</p>
                <p style='margin-bottom: 2px'> Customer Phone:  {$cardPayment->card_paymentable->phone}</p>
                <p style='margin-bottom: 2px'> Customer Email:  {$cardPayment->card_paymentable->email}</p>

                <p style='margin-bottom: 2px'> Date:  {$cardPayment->created_at}</p>
                <p> Best regards, </p>
                <p> Leverpay </p>
            ";
            $cardPayment->status = 'SUCCESSFUL';
            $cardPayment->save();
            $cardPayment->card_paymentable->update([
                'status' => 'SUCCESSFUL',
            ]);

            ZeptomailService::sendMailZeptoMail("Debit Alert" ,$html, $user->email);
            ZeptomailService::sendMailZeptoMail("Checkout Successful" ,$html2, "$cardPayment->card_paymentable->merchant->email");

            DB::commit();
            return $this->successfulResponse([], "Card payment successful done");
        }catch(\Exception $e){
            DB::rollBack();
            return $this->sendError($e->getMessage(),[],400);
        }

    }
}
