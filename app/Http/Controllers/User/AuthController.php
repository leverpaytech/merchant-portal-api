<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\BaseController;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Mail\SendEmailVerificationCode;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\Wallet;
use App\Services\CardService;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
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
    public function logout()
    {
        Auth::user()->token()->revoke();
        $data2=array(
            'activity' => 'User Logout',
            'user_id' => Auth::user()->id
        );

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
     *              required={"gender","dob","email","password", "first_name", "last_name","phone"},
     *              @OA\Property( property="first_name", type="string"),
     *              @OA\Property( property="last_name", type="string"),
     *              @OA\Property( property="other_name", type="string"),
     *              @OA\Property( property="gender", type="string"),
     *              @OA\Property( property="dob", type="string", format="date"),
     *              @OA\Property( property="email", type="string"),
     *              @OA\Property( property="phone", type="string"),
     *              @OA\Property( property="password", type="string"),
     *              @OA\Property( property="country_id", enum="[1]"),
     *              @OA\Property( property="state_id", enum="[1]"),
     *              @OA\Property( property="city_id", enum="[1]")
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
        // $data = $request->all();

        $data = $this->validate($request, [
            'first_name' => 'required',
            'last_name' => 'required',
            'other_name' => 'nullable',
            'dob' => 'required',
            'gender' => 'required',
            'email' => 'unique:users,email|required|email',
            'phone' => 'unique:users',
            'state_id' => 'required|integer',
            'city_id' => 'required|integer',
            'country_id' => 'required',
            'password' => ['required', Password::min(8)->symbols()->uncompromised() ]
        ]);

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
        $data['role_id']='0';
        $user = User::create($data);

        // send email

        $html = "
                <p>Hello {$data['first_name']} {$data['last_name']}</p>
                <p style='margin-bottom: 8px'>We are excited to have you here. Below is your verification token</p>
                <h2 style='margin-bottom: 8px'>
                    {$verifyToken}
                </h2>
        ";
        $sms = SmsService::sendMail("",$html, "LeveryPay Verification Code", $data['email']);

        //sent sign up notification to leverpay admin
        $html2 = "
            <2 style='margin-bottom: 8px'>User Details</h2>
            <div style='margin-bottom: 8px'>User's Name: {$data['first_name']} {$data['last_name']} </div>
            <div style='margin-bottom: 8px'>Email Address: {$data['email']} </div>
            <div style='margin-bottom: 8px'>Phone Number: {$data['phone']} </div>
        ";
        $to="contact@leverpay.io";
        
        SmsService::sendMail("", $html2, "new user sign up", $to);

        return $user;
    }
}
