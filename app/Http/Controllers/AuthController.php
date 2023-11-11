<?php

namespace App\Http\Controllers;

use App\Mail\GeneralMail;
use App\Models\Account;
use App\Models\Wallet;
use App\Services\CardService;
use App\Services\MerchantKeyService;
use App\Services\SmsService;
use Illuminate\Http\Request;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\{User,ActivityLog};
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendEmailVerificationCode;
use App\Mail\ForgotPasswordMail;
use App\Services\ProvidusService;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Validator;
class AuthController extends BaseController
{
    protected $userModel;

    /**
     * @OA\SecurityScheme(
     *     type="apiKey",
     *     in="header",
     *     securityScheme="bearer_token",
     *     name="Authorization"
     * )
     */
    public function __construct(User $user) {
        $this->userModel = $user;
    }

    public function testProvidus(Request $request){
        // return env('TERMII_API_KEY').'/PiPCreateDynamicAccountNumber';
        $req = ProvidusService::generateDynamicAccount('Timi Adekunle');
        if($req['requestSuccessful']){
            return $req;
        }else{
            return $req['responseMessage'];
        }

    }

    public function test(Request $request){
        $t = '0002';
        $sms = SmsService::sendSms("Dear User, Your Leverpay One-time Confirmation code is 4567 and it will expire in 10 minutes. Please do not share For enquiry: contact@leverpay.io", '234'.$request['phoneNumber']);
        // $sms = SmsService::sendMail("Hello Lekan, Welcome to LeverPay", "Testing LeverPay Mail", 'ilelaboyealekan@gmail.com');
        return $sms;
    }

    /**
     * @OA\Post(
     ** path="/api/v1/login",
     *   tags={"Authentication & Verification"},
     *   summary="Authentication",
     *   operationId="login Authentication",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"email", "password"},
     *              @OA\Property( property="email", type="string"),
     *              @OA\Property( property="password", type="string"),
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
     *   )
     *)
     **/
    public function login(Request $request)
    {
        $user = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string'
        ]);

        if(!Auth::attempt($user))
        {
            return $this->sendError("invalid login credentials",[], 401);
        }

        if(!Auth::user()->verify_email_status){
            return $this->sendError("Email verification required",[], 400);
        }

        if(Auth::user()->role_id == 1){
            Log::info('merchant role');

            $accessToken = Auth::user()->createToken('access_token', ['merchant']);
        }else{
            Log::info('user role');
            $accessToken = Auth::user()->createToken('access_token');
        }
        $user = Auth::user();

        if ($user->status==1)
        {
            $user->last_seen_at = Carbon::now()->format('Y-m-d H:i:s');
            $user->save();

            $data2['activity']="Login";
            $data2['user_id']=$user->id;

            ActivityLog::createActivity($data2);

            return $this->successfulResponse([
                "user" => new UserResource($user),
                "token" => $accessToken->accessToken,
                "expires_at" => Carbon::parse($accessToken->token->expires_at)->toDateTimeString()
            ], 'Logged in successfully');

        }else return $this->sendError('Your account has been deactivated, contact the admin',[],401);
    }
    /**
     * @OA\Post(
     ** path="/api/v1/resend-verification-email",
     *   tags={"Authentication & Verification"},
     *   summary="Resend email verification link",
     *   operationId="Resend email verification link",
     *
     *   @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"email"},
     *              @OA\Property( property="email", type="string")
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
     *   )
     *)
     **/
    public function resendVerificationEmail(Request $request)
    {
        /*\Artisan::call('route:cache');
        \Artisan::call('config:cache');
        \Artisan::call('cache:clear');
        \Artisan::call('view:clear');
        \Artisan::call('optimize:clear');*/

        $this->validate($request, [
            'email'=>'required|email'
        ]);
        $user = User::where('email', $request['email'])->first();
        if(!$user)
        {
           return $this->sendError('Email address not found',[],404);
        }
        if($user->verify_email_status){
            return $this->sendError('Email is already verified',[],400);
        }

        $verifyToken = rand(1000, 9999);

        $user->verify_email_token = $verifyToken;
        $user->save();

        $html = "
        <p>Hello {$user['first_name']},</p>
        <p style='margin-bottom: 8px'>
            We are excited to have you here. Below is your verification token
            </p>
            <h4 style='margin-bottom: 8px'>
                {$verifyToken}
            </h4>
        ";
        // Mail::to($request['email'])->send(new SendEmailVerificationCode($user['first_name'], $verifyToken));
        $sms = SmsService::sendMail("",$html, "LeveryPay Verification Code", $request['email']);

        return response()->json('Email sent sucessfully', 200);
    }

    /**
     * @OA\Post(
     ** path="/api/v1/verify-email",
     *   tags={"Authentication & Verification"},
     *   summary="verify email",
     *   operationId="verify email",
     *
     *   @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"email", "token"},
     *              @OA\Property( property="email", type="string"),
     *              @OA\Property( property="token", type="string")
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
     *   )
     *)
     **/
    public function verifyEmail(Request $request)
    {
        $this->validate($request, [
            'email'=>'required|email',
            'token'=>'required|string'
        ]);

        $user = User::where('email',$request['email'])
            ->where('verify_email_token', $request['token'])
            ->first();
        if(!$user){
            return $this->sendError("invalid token, please try again",[], 401);
        }

        if($user->role_id == 1){
            MerchantKeyService::createKeys($user->id);
            $details="Merchant User Sign Up";
        }else{
            CardService::createCard($user['id']);
            $details="New User Sign Up";
        }

        $user->verify_email_token = bin2hex(random_bytes(15));
        $user->verify_email_status = true;
        $user->save();



        $data2['activity']="VerifyEmail";
        $data2['user_id']=$user->id;
        ActivityLog::createActivity($data2);

        //sent sign up notification to leverpay admin
        $html2 = "
            <2 style='margin-bottom: 8px'>{$details}</h2>
            <div style='margin-bottom: 8px'>User's Name: {$user->first_name} {$user->last_name} </div>
            <div style='margin-bottom: 8px'>Email Address: {$user->email} </div>
            <div style='margin-bottom: 8px'>Phone Number: {$user->phone} </div>
        ";
        //$to="contact@leverpay.io";
        $to="abdilkura@gmail.com";

        SmsService::sendMail("", $html2, $details, $to);
        

        return $this->successfulResponse([], 'Email verified successfully');
    }

    /**
     * @OA\Post(
     ** path="/api/v1/forgot-password",
     *   tags={"Authentication & Verification"},
     *   summary="Send forgot password token",
     *   operationId="Send forgot password token",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"email"},
     *              @OA\Property( property="email", type="string")
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
     *   )
     *)
     **/
    public function sendForgotPasswordToken(Request $request)
    {
        $this->validate($request, [
            'email'=>'required|email'
        ]);
        $user = User::where('email', $request['email'])->first();
        if(!$user){
            return $this->sendError('Invalid email',[],400);
        }
        $verifyToken = rand(1000, 9999);

        $user->forgot_password_token = $verifyToken;
        $user->save();

        $html = "
        <p>Hello {$user['first_name']},</p>
        <p style='margin-bottom: 8px'>
        Below is your reset password token
            </p>
            <h4 style='margin-bottom: 8px'>
                {$verifyToken}
            </h4>
        ";

        // Mail::to($request['email'])->send(new ForgotPasswordMail($user['first_name'], $verifyToken));
        $sms = SmsService::sendMail("",$html, "LeveryPay Forgot Password Code", $request['email']);

        return response()->json('Email sent sucessfully', 200);
    }

    /**
     * @OA\Post(
     ** path="/api/v1/reset-password",
     *   tags={"Authentication & Verification"},
     *   summary="Reset password",
     *   operationId="Reset password",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"new_password","token"},
     *              @OA\Property( property="token", type="string"),
     *              @OA\Property( property="new_password", type="string"),
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
     *   )
     *)
     **/
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'new_password' => ['required', Password::min(8)->symbols()->uncompromised() ]
        ]);
        if ($validator->fails())
            return $this->sendError('Error',$validator->errors(),422);

        $user = User::where('forgot_password_token', $request['token'])->first();
        if(!$user){
            return $this->sendError('Invalid token',[],400);
        }
        $user->forgot_password_token=bin2hex(random_bytes(15));
        $user->password = bcrypt($request['new_password']);
        $user->save();
        return response()->json('Password reset successfully', 200);
    }


}
