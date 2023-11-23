<?php

namespace App\Services;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use App\Models\Card;
use Illuminate\Support\Facades\Hash;

class CardService
{
    public static function createCard($user_id){
        $rand = rand(1,999);
        $card = new Card();
        $card->user_id = $user_id;
        $card->cvv = $rand < 10 ? "00".$rand : ($rand > 10 && $rand  < 100 ? "0".$rand : $rand);
        // $card->pin = bcrypt($pin);
        $card->card_number='22'.rand(10,100).rand(1000,9999).rand(1000,9999).rand(1000,9999);
        $card->save();
        return $card;
    }

    public static function upgradeCardNumber($user_id, $card_type){
        $card = Card::where('user_id', $user_id)->first();
        $new_card = strval($card->card_number);
        if($card_type == 2){
            $new_card[0] = 1;
            $new_card[1] = 0;
        }else if($card_type == 3){
            $new_card[0] = 2;
            $new_card[1] = 3;
        }
        else if($card_type == 4){
            $new_card[0] = 9;
            $new_card[1] = 4;
        }else if($card_type == 5){
            $new_card[0] = 6;
            $new_card[1] = 5;
        }
        $card->card_number = $new_card;
        $card->save();
        return $card;
    }

    public function validateCredentials($card_no, $cvv, $pin, $expiry): bool
    {
        $check = Card::where('user_id',Auth::id())
            ->where('card_number',$card_no)
            ->where('cvv', $cvv)
            ->where('expiry', $expiry)
            ->where('expiry', '>', Carbon::now())
            ->first();

        if(!$check || !Hash::check($pin, $check->pin)){
            return false;
        }

        //code to check wallet balance goes here
        return true;
    }
}
