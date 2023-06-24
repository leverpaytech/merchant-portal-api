<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\BaseController;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Resources\CardResource;
use App\Models\ActivityLog;
use App\Models\Currency;
use App\Models\User;
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
    public function generateCard(Request $request){
        $this->validate($request, [
            'pin'=>'required|integer'
        ]);
        if(Auth::user()->card){
            return $this->sendError('Card has already been created',[],400);
        }
        $card = CardService::createCard($request['pin']);
        return new CardResource($card);
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
            return $this->sendError('Unauthorized Access',[],404);
        $userId = Auth::user()->id;
        $user = User::where('id', $userId)->with('currencies')->get()->first();

        if(!$user)
            return $this->sendError('User not found',[],404);
        return $this->successfulResponse($user,'User found successfully');
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
     *              required={"email","password", "first_name", "last_name","phone"},
     *              @OA\Property( property="first_name", type="string"),
     *              @OA\Property( property="last_name", type="string"),
     *              @OA\Property( property="email", type="string"),
     *              @OA\Property( property="phone", type="string"),
     *              @OA\Property( property="password", type="string"),
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

        /*$validator = Validator::make($data, [
            'name' => 'required',
            'address' => 'required',
            'email' => 'required',
            'phone' => 'required',
            'state' => 'required',
            'city' => 'nullable',
            'passport' => 'nullable'
        ]);*/

        if(!$user = User::find($userId))
            return $this->sendError('User not found',[],404);
        if(isset($request->email))
        {
            $validator = Validator::make($request->all(),[
                'email' =>  Rule::unique('users')->ignore($userId),
                //'unique:users,email|required|email',
            ]);
            if ($validator->fails())
                return $this->sendError('User with the same email exists already',$validator->errors(),400);

        }
        if(isset($request->passport))
        {
            try
            {
                //$newname= $userId.''.time().'.'.$request->passport->extension();
                //$request->passport->move(public_path('passports'), $newname);
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
