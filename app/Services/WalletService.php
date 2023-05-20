<?php

namespace App\Services;

use App\Models\Wallet;
use App\Models\User;

class WalletService
{
    public static function addToWallet($user_id, $amount){
        $wallet = Wallet::where('user_id', $user_id)->first();
        if(!$wallet){
            $wallet = new Wallet();
            $wallet->user_id = $user_id;
            $wallet->save();
        }

        $wallet->amount = floatval($wallet->amount) + floatval($amount);
        $wallet->withdrawable_amount = floatval($wallet->withdrawable_amount) + floatval($amount);
        $wallet->save();

        return $wallet;
    }
}
