<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Investment;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Webhook;
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

        DB::beginTransaction();

        try {
        $web = new Webhook;
        $web->raw = json_encode($request->all());
        $web->sessionId = hexdec(Str::random(30));
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

        }else{
            WalletService::addToWallet($user->id, $request['transactionAmount']);
            $trans->balance = floatval($user->wallet->withdrawable_amount) + floatval($request['transactionAmount']);
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
            SmsService::sendMail('', $html, 'Invoice Completed', $user->email);
            $account->accountNumber = rand(1000,9999).'_'.$request['accountNumber'];
            $account->save();
        }else{
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

        return [
            'requestSuccessful'=>true,
            'sessionId'=>$web->sessionId,
            'responseMessage'=>'success',
            'responseCode'=>'01'
        ];
    }
}
