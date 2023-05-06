<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\{User,ActivityLog};
use Illuminate\Support\Facades\Mail;
use App\Mail\SendEmailVerificationCode;
use App\Mail\ForgotPasswordMail;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Validator;
class AuthController extends BaseController
{
    protected $userModel;

    /**
     * @OA\SecurityScheme(
     *     type="apiKey",
     *     in="header",
     *     securityScheme="api_key",
     *     name="Authorization"
     * )
     */
    public function __construct(User $user) {
        $this->userModel = $user;
    }

    /************************merchant services*********************************** */
    /**
     * @OA\Post(
     ** path="/api/v1/login",
     *   tags={"Authentication & Verification"},
     *   summary="Authentication",
     *   operationId="login Authentication",
     ** path="/api/merchant/login",
     *   tags={"Merchant"},
     *   summary="Authentication",
     *   operationId="merchant login",
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
            'email' => 'required|string',
            'password' => 'required|string'
        ]);

        if(!Auth::attempt($user))
        {
            return $this->sendError("invalid login credentials",[], 401);
        }

        if(!Auth::user()->verify_email_status){
            return $this->sendError("Email verification required",[], 400);
        }
        $accessToken = Auth::user()->createToken('access_token');

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
     * @OA\Get(
     ** path="/api/merchants/logout",
     *   tags={"Authentication"},
     *   summary="Logout",
     *   operationId="logout",
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
        if($user->verify_email_status){
            return $this->sendError('Email is already verified',[],400);
        }

        $verifyToken = rand(1000, 9999);

        $user->verify_email_token = $verifyToken;
        $user->save();

        Mail::to($request['email'])->send(new SendEmailVerificationCode($user['name'], $verifyToken));

        return response()->json('Email sent sucessfully', 200);
    }

    public function verifyEmail(){
        $token = request()->query('token');
        if(!$token){
            return $this->sendError('Token field is required',[],401);
        }

        $user = User::where('email',$request['email'])
                        ->where('verify_email_token', $request['token'])
                        ->first();
        if(!$user){
            return $this->sendError("invalid token, please try again",[], 401);
        }

        $user->verify_email_token = bin2hex(random_bytes(15));
        $user->verify_email_status = true;
        $user->save();

        $data2['activity']="VerifyEmail";
        $data2['user_id']=$user->id;
        ActivityLog::createActivity($data2);

        return $this->successfulResponse(new UserResource($user), 'Email verified successfully');

    }

    public function sendForgotPasswordToken(Request $request){
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
        Mail::to($request['email'])->send(new ForgotPasswordMail($user['name'], $verifyToken));
        return response()->json('Email sent sucessfully', 200);
    }

    public function resetPassword(Request $request){
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
