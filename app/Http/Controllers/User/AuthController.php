<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\BaseController;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Mail\SendEmailVerificationCode;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Validator;

class AuthController extends BaseController
{
    /**
     * @OA\Get(
     ** path="/api/v1/user/logout",
     *   tags={"User"},
     *   summary="User Logout",
     *   operationId="User logout",
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
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden"
     *      ),
     *     security={
     *       {"bearer_token": {}}
     *     }
     *)
     **/
    public function logout(Request $request)
    {
        Auth::user()->token()->revoke();
        $data2['activity']="User Logout";
        $data2['user_id']=Auth::user()->id;

        ActivityLog::createActivity($data2);

        return response([ 'message' => 'logged out successfully'],200);
    }

    /**
     * @OA\Post(
     ** path="/api/v1/user/signup",
     *   tags={"User"},
     *   summary="Create a new user account",
     *   operationId="create a new user",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"email","password", "first_name", "last_name","phone","country_id"},
     *              @OA\Property( property="first_name", type="string"),
     *              @OA\Property( property="last_name", type="string"),
     *              @OA\Property( property="email", type="string"),
     *              @OA\Property( property="phone", type="string"),
     *              @OA\Property( property="country_id", enum="[Nigeria]"),
     *              @OA\Property( property="password", type="string"),
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
    public function create(Request $request)
    {
        $data = $request->all();

        $validator = Validator::make($data, [
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => 'unique:users,email|required|email',
            'phone' => 'unique:users',
            'password' => ['required', Password::min(8)->symbols()->uncompromised() ]
        ]);

        if ($validator->fails())
            return $this->sendError('Error',$validator->errors(),422);


        //$password = Str::random(6);
        //$data['password'] = Hash::make($password);

        $user = $this->createUser($data);

        $data2['activity']="User Sign Up";
        $data2['user_id']=$user->id;

        ActivityLog::createActivity($data2);

        return $this->successfulResponse(new UserResource($user), 'User successfully sign-up');
    }

    private function createUser($data)
    {
        $verifyToken = rand(1000, 9999);
        $data['verify_email_token'] = $verifyToken;
        $data['password'] = bcrypt($data['password']);
        $user = User::create($data);

        $wallet = new Wallet();
        $wallet->user_id = $user->id;
        $wallet->save();

        // send email
        Mail::to($data['email'])->send(new SendEmailVerificationCode($data['first_name'].' '.$data['last_name'], $verifyToken));

        return $user;
    }
}
