<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\BaseController;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Resources\CardResource;
use App\Models\ActivityLog;
use App\Models\Currency;
use App\Models\{User,Transaction};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Services\CardService;


class UserController extends BaseController
{
    protected $userModel;

    public function __construct(User $user)
    {
        $this->userModel = $user;
    }

    /**
     * @OA\Post(
     ** path="/api/v1/user/generate-card",
     *   tags={"User"},
     *   summary="Generate new card",
     *   operationId="Add/Generate new card",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"pin"},
     *              @OA\Property( property="pin", type="string"),
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
    // public function generateCard(Request $request){

    //     if(Auth::user()->card){
    //         return $this->sendError('Card has already been created',[],400);
    //     }
    //     $card = CardService::createCard(Auth::id());
    //     return new CardResource($card);
    // }

    /**
     * @OA\Get(
     ** path="/api/v1/user/get-user-profile",
     *   tags={"User"},
     *   summary="Get user profile",
     *   operationId="get user profile details",
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
    public function getUserProfile()
    {
        if(!Auth::user()->id)
            return $this->sendError('Unauthorized Access',[],401);
        $userId = Auth::user()->id;
        $user = User::where('id', $userId)->with('currencies')->with('state')->with('city')->get()->first();
        $wallet=Auth::user()->wallet;
        $user->wallet_balance=$wallet->amount;
        $getV1=Transaction::where('user_id',$userId)->where('type','credit')->sum('amount');
        $user->total_save=$getV1;
        $getV2=Transaction::where('user_id',$userId)->where('type','debit')->sum('amount');
        $user->total_spending=$getV2;


        if(!$user)
            return $this->sendError('User not found',[],404);
        return $this->successfulResponse(new UserResource($user));
    }

    /**
     * @OA\Post(
     ** path="/api/v1/user/update-user-profile",
     *   tags={"User"},
     *   summary="Update user profile",
     *   operationId="Update user profile",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              @OA\Property( property="other_name", type="string"),
     *              @OA\Property( property="other_email", type="string"),
     *              @OA\Property( property="other_phone", type="string"),
     *              @OA\Property( property="primary_email", type="string"),
     *              @OA\Property( property="primary_phone", type="string"),
     *              @OA\Property( property="gender", type="string"),
     *              @OA\Property( property="country_id", enum="[1]"),
     *              @OA\Property( property="state_id", enum="[1]"),
     *              @OA\Property( property="city_id", enum="[1]"),
     *              @OA\Property( property="passport", type="file"),
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
    public function updateUserProfile(Request $request)
    {
        $userId = Auth::user()->id;

        $data = $request->all();

        $validator = Validator::make($data, [
            'other_name' => 'nullable',
            'other_email' => 'nullable',
            'other_email' => 'nullable',
            'other_phone' => 'nullable',
            'country_id' => 'nullable',
            'state_id' => 'nullable',
            'city_id' => 'nullable',
            'address' => 'nullable',
            'gender' => 'nullable',
            'passport' => 'nullable|mimes:jpeg,png,jpg,gif|max:2048'
        ]);
        
        if ($validator->fails())
        {
            return $this->sendError('Error',$validator->errors(),422);
        }
        if(!empty($data['passport']))
        {
            try
            {
                $newname = cloudinary()->upload($request->file('passport')->getRealPath(),
                    ['folder'=>'leverpay/profile_picture']
                )->getSecurePath();

                $data['passport']= $newname;

                //add new image
                //$request->passport->move(public_path('passports'), $newname);
            } catch (\Exception $ex) {
                return $this->sendError($ex->getMessage());
            }
        }
        $user = $this->userModel->updateUser($data,$userId);
        // $user->firstname
        $data2['activity']="User Profile Update";
        $data2['user_id']=$userId;

        ActivityLog::createActivity($data2);
        return $this->successfulResponse(new UserResource($user), 'User profile successfully updated');
    }

    /**
     * @OA\Get(
     ** path="/api/v1/user/get-user-currencies}",
     *   tags={"Merchant"},
     *   summary="Get user's currencies",
     *   operationId="get user's currencies",
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
    public function getUserCurrencies(){
        return $this->successfulResponse(Auth::user()->currencies,'');
    }

    /**
     * @OA\Post(
     ** path="/api/v1/user/add-currencies",
     *   tags={"User"},
     *   summary="Add user curriencies",
     *   operationId="Add user curriencies",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"curriencies"},
     *              @OA\Property( property="curriencies", type="array",@OA\Items( type="integer"), )
     *          ),
     *      ),
     *   ),
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
    public function addCurrencies(Request $request)
    {
        $this->validate($request, [
            'currencies'=>'required|array'
        ]);
        $user = Auth::user()->currencies()->sync($request['currencies']);
        return $this->successfulResponse(Auth::user()->currencies,'');
    }

}
