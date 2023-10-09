<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Webhook;
use App\Services\ProvidusService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class WebhookController extends Controller
{
    public function providus(Request $request){
        // come back to validate amount
        if(!$request->hasHeader('X-Auth-Signature') || $request->header('X-Auth-Signature') != ProvidusService::$signature){
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

        return [
            'requestSuccessful'=>true,
            'sessionId'=>$web->sessionId,
            'responseMessage'=>'success',
            'responseCode'=>'01'
        ];
    }
}
