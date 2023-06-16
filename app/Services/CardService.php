<?php

namespace App\Services;
use Illuminate\Support\Facades\Auth;
use App\Models\Card;
use Illuminate\Support\Facades\Hash;

class CardService
{
    public static function createCard($pin){
        $rand = rand(1,999);
        $card = new Card();
        $card->user_id = Auth::id();
        $card->cvv = $rand < 10 ? "00".$rand : ($rand > 10 && $rand  < 100 ? "0".$rand : $rand);
        $card->pin = bcrypt($pin);
        $card->card_number=rand(1000,9999).rand(1000,9999).rand(1000,9999).rand(1000,9999);
        $card->save();
        return $card;
    }

    public function validateCredentials($card_no, $cvv, $pin){
        $check = Card::where('user_id',Auth::id())->where('card_number',$card_no)->where('cvv', $cvv)->first();
        if(!$check || !Hash::check($pin, $check->pin)){
            return ['status'=>false, 'message'=>'Invalid card details'];
        }

        //code to check wallet balance goes here

        return ['status'=>true];
    }
}
