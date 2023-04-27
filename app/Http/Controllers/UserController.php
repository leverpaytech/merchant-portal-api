<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\ActivityLog;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use App\Http\Resources\GeneralResource;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;

class UserController extends BaseController
{
    protected $userModel;

    public function __construct(User $user)
    {
        $this->userModel = $user;
    }

    /**
     * @OA\Get(
     ** path="/api/merchants/",
     *   tags={"Merchants"},
     *   summary="Get all merchants",
     *   operationId="get all merchants",
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
    public function index()
    {
        return $this->successfulResponse(User::where('role_id',3)->get(), "Merchants retrieved successfully");
    }

    /**
     * @OA\Get(
     ** path="/api/merchants/get/{id}",
     *   tags={"Merchants"},
     *   summary="Get a merchant",
     *   operationId="get a merchant",
     *
     *   @OA\Parameter(
     *      name="id",
     *      in="path",
     *      required=true,
     *      @OA\Schema(
     *           type="string"
     *      )
     *   ),
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
    public function get($id)
    {
        $user = User::find($id);

        if(!$user)
            return $this->sendError('Merchant not found',[],404);
        return $this->successfulResponse($user,'Merchant found successfully');
    }

    /**
     * @OA\Post(
     ** path="/api/user/register",
     *   tags={"User"},
     *   summary="Create a new merchant account",
     *   operationId="create a new merchant",
     *
     *   @OA\Parameter(
     *      name="name",
     *      in="query",
     *      required=true,
     *      @OA\Schema(
     *          type="string"
     *      )
     *   ),
     *   @OA\Parameter(
     *      name="address",
     *      in="query",
     *      required=true,
     *      @OA\Schema(
     *          type="string"
     *      )
     *   ),
     *   @OA\Parameter(
     *      name="email",
     *      in="query",
     *      required=false,
     *      @OA\Schema(
     *          type="string"
     *      )
     *   ),
     *    @OA\Parameter(
     *      name="phone",
     *      in="query",
     *      required=true,
     *      @OA\Schema(
     *           type="string"
     *      )
     *   ),
     *   @OA\Parameter(
     *      name="password",
     *      in="query",
     *      required=false,
     *      @OA\Schema(
     *          type="string"
     *      )
     *   ),
     *   @OA\Parameter(
     *      name="state",
     *      in="query",
     *      required=false,
     *      @OA\Schema(
     *          type="string"
     *      )
     *   ),
     *   @OA\Parameter(
     *      name="city",
     *      in="query",
     *      required=false,
     *      @OA\Schema(
     *          type="string",
     *      )
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
    public function create(Request $request)
    {
        $data = $request->all();

        $validator = Validator::make($data, [
            'name' => 'required',
            'address' => 'required',
            'email' => 'unique:users,email|required|email',
            'phone' => 'unique:users',
            'state' => 'required',
            'city' => 'required',
            'password' => 'required'
        ]);

        if ($validator->fails())
            return $this->sendError('Error',$validator->errors());

        if (User::where('email',$request->email)->first())
            return $this->sendError('Merchant with same email exists');

        //$password = Str::random(6);
        //$data['password'] = Hash::make($password);

        $user = $this->createUser($data);

        $data2['activity']="Sign Up";
        $data2['user_id']=$user->id;

        ActivityLog::createActivity($data2);

        return $this->successfulResponse(new UserResource($user), 'Merchant successfully sign-up');
    }

    private function createUser($data)
    {
        $data['status'] = 1;
        $data['role_id'] = 3;
        $user = $this->userModel->createUser($data);

        $email = $user->email;

        $password = $user->password;

        // send email
        $details['email'] = $email;
        $details['password'] = $password;
        $details['user'] = $user;

        // dispatch(new \App\Jobs\NewUserJob($details));

        return $user;
    }

        /**
     * @OA\Patch(
     ** path="/api/user/update-merchant-profile/{id}",
     *   tags={"User"},
     *   summary="Update merchant profile",
     *   operationId="update merchant profile",
     *
     *   @OA\Parameter(
     *      name="name",
     *      in="query",
     *      required=true,
     *      @OA\Schema(
     *          type="string"
     *      )
     *   ),
     *   @OA\Parameter(
     *      name="address",
     *      in="query",
     *      required=true,
     *      @OA\Schema(
     *          type="string"
     *      )
     *   ),
     *   @OA\Parameter(
     *      name="email",
     *      in="query",
     *      required=false,
     *      @OA\Schema(
     *          type="string"
     *      )
     *   ),
     *    @OA\Parameter(
     *      name="phone",
     *      in="query",
     *      required=true,
     *      @OA\Schema(
     *           type="string"
     *      )
     *   ),
     *   @OA\Parameter(
     *      name="state",
     *      in="query",
     *      required=false,
     *      @OA\Schema(
     *          type="string"
     *      )
     *   ),
     *   @OA\Parameter(
     *      name="city",
     *      in="query",
     *      required=false,
     *      @OA\Schema(
     *          type="string",
     *      )
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
    public function updateUser(Request $request, $id)
    {

        $user = $this->userModel->find($id);

        $data = $request->validate(
          [
            'name' => 'required',
            'address' => 'required',
            'phone' => 'unique:users',
            'state' => 'required',
            'city' => 'required'
        ]);

        $user->update($data);

        $log['activity']= 'Merchant Updated';
        $log['description']= 'User Profile Updated';
        $log['user_id']= Auth::user()->id;

        ActivityLog::createActivity($log);

        return $this->successfulResponse(new UserResource($user), 'Merchant profile updated successfully');

    }


}
