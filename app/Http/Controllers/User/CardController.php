<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\BaseController;
use App\Http\Resources\CardResource;
use App\Models\Card;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CardController extends BaseController
{
     /**
     * @OA\Post(
     ** path="/api/v1/user/set-pin",
     *   tags={"User"},
     *   summary="Set Card Pin",
     *   operationId="Set Card Pin",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"pin"},
     *              @OA\Property( property="pin", type="number"),
     *          ),
     *      ),
     *   ),
     *
     *   @OA\Response(
     *      response=200,
     *       description="Success",
     *      @OA\MediaType(
     *           mediaType="application/json",
     *      )
     *   ),
     *   @OA\Response(
     *      response=401,
     *       description="Unauthenticated"
     *   ),
     *   @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     *   @OA\Response(
     *      response=404,
     *      description="not found"
     *   ),
     *   @OA\Response(
     *      response=403,
     *      description="Forbidden"
     *   ),
     *   security={
     *       {"bearer_token": {}}
     *   }
     *)
     **/

    public function setPin(Request $request){
        $this->validate($request, [
            'pin'=>'required|numeric|min:1000|max:9999'
        ],[
            'pin.min'=>'Pin must be four digit numbers',
            'pin.max'=>'Pin must be four digit numbers',
        ]);
        $pin = (string)$request['pin'];
        $card = Card::where('user_id', Auth::id())->first();
        if(!$card){
            return $this->sendError('Card does not exist, please contact the admin');
        }
        if($card->pin){
            return $this->sendError('Invalid request, Pin is already set',[], 400);
        }

        $card->pin = bcrypt($pin);
        $card->save();

        return $this->successfulResponse([], 'Pin set successfully');
    }

     /**
     * @OA\Get(
     ** path="/api/v1/user/get-card",
     *   tags={"User"},
     *   summary="Get card",
     *   operationId="get card",
     *
     *
     *   @OA\Response(
     *      response=200,
     *       description="Success",
     *     ),
     *     security={
     *       {"bearer_token": {}}
     *     }
     *
     *)
     **/
    public function getCard()
    {
        if(!Auth::user()->card){
            return $this->sendError('No Available card',[],404);
        }
        return new CardResource(Auth::user()->card);
    }

    public function upgradeCard(Request $request){
        $this->validate($request, [
            'membership'=>'required|integer',
            'document_type'=>'required|integer',
            'document' => 'required|mimes:jpeg,png,jpg,gif,pdf|max:3048'
        ]);

        $uploadUrl = cloudinary()->upload($request->file('document')->getRealPath(),
            ['folder'=>'leverpay/kyc']
        )->getSecurePath();

    }
}
