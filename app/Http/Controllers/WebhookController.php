<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Checkout;
use App\Models\Investment;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Webhook;
use App\Models\ExchangeRate;
use App\Services\ProvidusService;
use App\Services\SmsService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class WebhookController extends Controller
{
    public function providus(Request $request){
        // come back to validate amount
        if(!$request->hasHeader('X-Auth-Signature') || strtolower($request->header('X-Auth-Signature')) != strtolower(env('PROVIDUS_X_AUTH_SIGNATURE'))){
            return [
                'requestSuccessful'=>true,
                'sessionId'=>$request['sessionId'],
                'responseMessage'=>'rejected',
                'responseCode'=>'02'
            ];
        }

        $validator = Validator::make($request->all(), [
            'settlementId'=>'required|unique:webhooks,settlementId',
            'sessionId' => 'required|unique:webhooks,sessionId',
            'transactionAmount'=>'required|numeric|min:0',
        ]);

        if ($validator->fails()){
            return [
                'requestSuccessful'=>true,
                'sessionId'=>$request['sessionId'],
                'responseMessage'=>'duplicate',
                'responseCode'=>'01'
            ];
        }

        $account = Account::where('accountNumber', $request['accountNumber'])->first();
        if(!$account){
            return [
                'requestSuccessful'=>true,
                'sessionId'=>$request['sessionId'],
                'responseMessage'=>'rejected',
                'responseCode'=>'02'
            ];
        }

        $account->amount_paid = $request['transactionAmount'];

        // DB::beginTransaction();

        // try {

        $web = new Webhook;
        $web->raw = json_encode($request->all());
        $web->sessionId = $request['sessionId'];
        $web->bankSessionId = $request['sessionId'];
        $web->accountNumber = $request['accountNumber'];
        $web->tranRemarks = $request['tranRemarks'];
        $web->amount = $request['transactionAmount'];
        $web->settledAmount = $request['settledAmount'];
        $web->feeAmount = $request['feeAmount'];
        $web->vatAmount = $request['vatAmount'];
        $web->currency = $request['currency'];
        $web->transRef = $request['initiationTranRef'];
        $web->settlementId = $request['settlementId'];
        $web->sourceAccountNumber = $request['sourceAccountNumber'];
        $web->sourceAccountName = $request['sourceAccountName'];
        $web->sourceBankName = $request['sourceBankName'];
        $web->channelId = $request['channelId'];
        $web->tranDateTime = $request['tranDateTime'];
        $web->bank = 'providus';
        $web->save();

        $user = User::find($account->user_id);

        $trans = new Transaction;
        $trans->user_id = $account->user_id;
        $trans->reference_no = $request['sessionId'];
        $trans->tnx_reference_no=$request['initiationTranRef'];
        $trans->amount =$request['transactionAmount'];
        $trans->type = 'credit';
        $trans->merchant = 'providus';
        $trans->status = 1;


        if($account->type == 'investment'){
            $invest = Investment::where('accountNumber', $request['accountNumber'])->first();
            $invest->amount_paid = $request['transactionAmount'];
            $invest->status = 1;
            $invest->save();
            $trans->balance = $user->wallet->withdrawable_amount;
            $trans->transaction_details = json_encode([
                'investment_id'=>$invest->id,
            ]);

            $html = "
            <p>Hello {$user['first_name']} {$user['last_name']},</p>
            <p style='margin-bottom: 8px'>
                Your investment of {$request['transactionAmount']} was successful. Login into your account to track your investment
                </p>
            ";

            SmsService::sendMail('', $html, 'Investment Successful', $user['email']);

        }else if($account->type == 'checkout'){
            $checkout = Checkout::where('uuid', $account->model_id)->first();
            $checkout->status = 'SUCCESSFUL';
            $checkout->save();
            $html = "
            <p>Hello {$checkout['first_name']} {$checkout['last_name']},</p>
            <p style='margin-bottom: 8px'>
                You have successfully paid {$checkout['amount']} to {$checkout->merchant->merchant->business_name}
                </p>
            ";

            $html2 = "
            <p>Hello {$checkout->merchant->merchant->business_name},</p>
            <p style='margin-bottom: 8px'>
            {$checkout['first_name']} {$checkout['last_name']} has paid {$checkout['amount']} to you. Below is the payment details.
                </p>
                <p> Currency: {$checkout['currency']}</p>
                <p> Amount: {$checkout['amount']}</p>
                <p> Product: {$checkout['product']}</p>
                <p> Merchant Reference: {$checkout['merchant_reference']}</p>
                <p> Customer Name: {$checkout['first_name']} {$checkout['last_name']}</p>
                <p> Email: {$checkout['email']}</p>
                <p> Phone: {$checkout['phone']}</p>
                <p> Payment Url: {$checkout['authorization_url']}</p>
            ";
            SmsService::sendMail('', $html, 'Payment Receipt', $checkout['email']);
            SmsService::sendMail('', $html2, 'Payment Confirmation', $checkout->merchant->email);
        }else{
            $rates = ExchangeRate::latest()->first();
            // #100 is the bank VAT fee
            // $t_amt = floatval($request['transactionAmount']) - 100;
            $t_amt = floatval($request['transactionAmount']) - floatval($rates->funding_rate);
            WalletService::addToWallet($user->id, $t_amt);
            $trans->balance = floatval($user->wallet->withdrawable_amount) + floatval($t_amt);
        }

        $trans->extra = json_encode([
            'webhook'=>json_encode($request->all())
        ]);
        $trans->save();

        if($account->type == 'investment'){
            $html = "<p style='margin-bottom: 8px'>
                    Dear {$user->first_name},
                </p>
                <p style='margin-bottom: 10px'>An investment of ₦{$request['transactionAmount']} has been completed</p>

                <p> Best regards, </p>
                <p> Leverpay </p>
            ";
            SmsService::sendMail('', $html, 'Investment Completed', $user->email);
            $account->accountNumber = rand(1,9999999999).'_'.$request['accountNumber'];
            $account->status = 0;

        }else{
            if($account->type == 'topup' || $account->type == 'checkout' || $account->type == 'invoice'){
                $account->accountNumber = rand(1,9999999999).'_'.$request['accountNumber'];
                $account->status = 0;
            }
            $html = "<p style='margin-bottom: 8px'>
                    Dear {$user->first_name},
                </p>
                <p style='margin-bottom: 10px'>An amount of ₦{$request['transactionAmount']} has been credited to your wallet</p>
                <p style='margin-bottom: 2px'> Sender Account Number:  {$request['sourceAccountNumber']}</p>
                <p style='margin-bottom: 2px'> Sender Account Name:  {$request['sourceAccountName']}</p>
                <p style='margin-bottom: 2px'> Sender Bank Name:  {$request['sourceBankName']}</p>
                <p style='margin-bottom: 2px'> Date:  {$request['tranDateTime']}</p>
                <p> Best regards, </p>
                <p> Leverpay </p>
            ";
            SmsService::sendMail('', $html, 'Wallet Credit', $user->email);
        }

        $account->save();

        $html = "<p style='margin-bottom: 8px'>
                    Providus Credit Alert ({$user->first_name} {$user->last_name}),
                </p>
                <p style='margin-bottom: 10px'>{$user->first_name} deposited ₦{$request['transactionAmount']} into providus</p>
                <p style='margin-bottom: 2px'> Sender Account Number:  {$request['sourceAccountNumber']}</p>
                <p style='margin-bottom: 2px'> Sender Account Name:  {$request['sourceAccountName']}</p>
                <p style='margin-bottom: 2px'> Sender Bank Name:  {$request['sourceBankName']}</p>
                <p style='margin-bottom: 2px'> Date:  {$request['tranDateTime']}</p>
                <p> Best regards, </p>
                <p> Leverpay </p>
            ";

            //Send Mail and message to leverpay admin email & phone after successful credit
        SmsService::sendMail('', $html, "Providus Credit Alert ({$user->first_name} {$user->last_name})", 'funmi@leverpay.io');
        SmsService::sendSms("Providus Account Credit, User: {$user->first_name} {$user->last_name}, Amount: {$request['transactionAmount']}, Type: {$account->type}", '2347063415220');
        return [
            'requestSuccessful'=>true,
            'sessionId'=>$request['sessionId'],
            'responseMessage'=>'success',
            'responseCode'=>'00'
        ];
        // }catch(\Exception $e){
        //     DB::rollBack();
        //     return [
        //         'requestSuccessful'=>true,
        //         'sessionId'=>$request['sessionId'],
        //         'responseMessage'=>'rejected',
        //         'responseCode'=>'02'
        //     ];
        // }
    }
}
