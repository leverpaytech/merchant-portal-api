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
        $card->card_number=rand(1000,9999).rand(1000,9999).rand(1000,9999).rand(1000,9999);
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
