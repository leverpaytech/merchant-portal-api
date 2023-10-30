<?php

namespace App\Services;

use App\Models\Wallet;

class WalletService
{
    public static function addToWallet($user_id, $amount, $currency='naira'){
        $wallet = Wallet::where('user_id', $user_id)->first();
        if(!$wallet){
            $wallet = new Wallet();
            $wallet->user_id = $user_id;
            $wallet->save();
        }

        if($currency=='naira'){
            $wallet->amount = floatval($wallet->amount) + floatval($amount);
            $wallet->withdrawable_amount = floatval($wallet->withdrawable_amount) + floatval($amount);
        }else{
            $wallet->dollar = floatval($wallet->dollar) + floatval($amount);
        }
        $wallet->save();

        return $wallet;
    }

    public static function subtractFromWallet($user_id, $amount, $currency='naira'){
        $wallet = Wallet::where('user_id', $user_id)->first();
        if(!$wallet){
            $wallet = new Wallet();
            $wallet->user_id = $user_id;
            $wallet->save();
        }
        if($currency=='naira'){
            $wallet->amount = floatval($wallet->amount) - floatval($amount);
            $wallet->withdrawable_amount = floatval($wallet->withdrawable_amount) - floatval($amount);
        }else{
            $wallet->dollar = floatval($wallet->amount) - floatval($amount);
        }
        $wallet->save();

        return $wallet;
    }

}
