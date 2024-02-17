<?php

namespace App\Services;

use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WalletService
{
    public static function addToWallet($user_id, $amount, $currency='naira'){
        // try{
        //     DB::beginTransaction();
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
        //     DB::commit();

        //     return true;
        // }catch(\Exception $e){
        //     $err = [
        //         "user_id" => $user_id,
        //         'msg' => $e->getMessage()
        //     ];
        //     Log::info(json_encode($err));
        //     DB::rollBack();
        //     return false;
        // }
    }

    public static function subtractFromWallet($user_id, $amount, $currency='naira'){
        try{
            DB::beginTransaction();
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
            DB::commit();

            return true;
        }catch(\Exception $e){
            $err = [
                "user_id" => $user_id,
                'msg' => $e->getMessage()
            ];
            Log::info(json_encode($err));
            DB::rollBack();
            return false;
        }
    }
}
