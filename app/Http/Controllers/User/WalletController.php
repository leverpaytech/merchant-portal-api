<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Webpatser\Uuid\Uuid;

class WalletController extends Controller
{
    public function fundWallet(Request $request){
        $this->validate($request, [
            'amount'=>'required|numeric'
        ]);

        $amount = $request['amount'] * 100;

        // $user = Auth::user();
        $user = User::find(1);
        $reference = Uuid::generate()->string;
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.env('PAYSTACK_SECRET_TEST_KEY'),
            'Cache-Control'=> 'no-cache',
            'content-type'=>'application/json'
        ])->post('https://api.paystack.co/transaction/initialize', [
            'email' => $user->email,
            'amount' => $amount,
            'reference'=>$reference
        ]);

        $trans = new Transaction();
        $trans->user_id = $user->id;
        $trans->amount = $request['amount'];
        $trans->reference_no = $reference;
        $trans->type = 'credit';
        $trans->save();

        return($response);
    }
}
