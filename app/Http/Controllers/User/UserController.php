<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UserController extends Controller
{
    protected $userModel;

    public function __construct(User $user)
    {
        $this->userModel = $user;
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
     *       {"api_key": {}}
     *     }
     *
     *)
     **/
    public function getUserProfile()
    {
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
     *       {"api_key": {}}
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
                $newname= $userId.''.time().'.'.$request->passport->extension();
                $request->passport->move(public_path('passports'), $newname);

                $data['passport']= $newname;

                //add new image
                $request->passport->move(public_path('passports'), $newname);
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
     *       {"api_key": {}}
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
     *       {"api_key": {}}
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

    public function userLedgerDetails()
    {

    }
}
