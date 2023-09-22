<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Mail\SendEmailVerificationCode;
use App\Models\{ActivityLog,AdminLogin};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Mail\ForgotPasswordMail;

class AdminLoginController extends BaseController
{
    /**
     * @OA\Post(
     ** path="/api/v1/admin/admin-login",
     *   tags={"Admin"},
     *   summary="Authentication",
     *   operationId="Admin login Authentication",
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
        $data = $request->all();

        $validator = Validator::make($data, [
            'email'=>'required|email',
            'password'=>'required|string'
        ]);

        if ($validator->fails())
        {
            return $this->sendError('Error',$validator->errors(),422);
        }
        
        if(!Auth::guard('admin')->attempt($data))
        {
            return $this->sendError("invalid login credentials",[], 401);
            exit();
        }
        
        config(['auth.guards.api.provider' => 'admins']);
        
        
        $admin = AdminLogin::find(auth()->guard('admin')->user()->id);
        $admin->last_seen_at = Carbon::now()->format('Y-m-d H:i:s');
        $admin->save();

        $data2['activity']="Admin Login";
        $data2['user_id']=$admin->id;

        ActivityLog::createActivity($data2);

        $token = auth()->guard('admin')->user()->createToken('access_token');
        
        return $this->successfulResponse([
            "admin" => $admin,
            "token" => $token->accessToken,
            "expires_at" => Carbon::parse($token->token->expires_at)->toDateTimeString()
        ], 'Logged in successfully');


        
    }
    
    /**
     * @OA\Post(
     ** path="/api/v1/admin/admin-forgot-password",
     *   tags={"Admin"},
     *   summary="Send forgot password token",
     *   operationId="Admin send forgot password token",
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
        $admin = AdminLogin::where('email', $request['email'])->first();
        if(!$admin){
            return $this->sendError('Invalid email',[],400);
        }
        $verifyToken = rand(1000, 9999);

        $admin->forgot_password_token = $verifyToken;
        $admin->save();
        Mail::to($request['email'])->send(new ForgotPasswordMail($admin['first_name'], $verifyToken));
        return response()->json('Email sent sucessfully', 200);
    }

    /**
     * @OA\Post(
     ** path="/api/v1/admin/admin-reset-password",
     *   tags={"Admin"},
     *   summary="Reset password",
     *   operationId="Admin reset password",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"new_password","confirm_new_password"},
     *              @OA\Property( property="new_password", type="string"),
     *              @OA\Property( property="confirm_new_password", type="string"),
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
            'new_password' => ['required', Password::min(8)->symbols()->uncompromised() ],
            'confirm_new_password' => ['required', Password::min(8)->symbols()->uncompromised() ]
        ]);

        if ($validator->fails())
            return $this->sendError('Error',$validator->errors(),422);

        if($request['new_password'] != $request['confirm_new_password'])
        {
            return $this->sendError('Passwords does not match',[],400);
        }
        $dminLogin = AdminLogin::where('id', 1)->get()->first();
        $dminLogin->forgot_password_token=bin2hex(random_bytes(15));
        $dminLogin->password = bcrypt($request['new_password']);
        $dminLogin->save();

        return response()->json('Password  successfully reset', 200);
    }

    /**
     * @OA\Post(
     ** path="/api/v1/admin/admin-verify-email",
     *   tags={"Admin"},
     *   summary="Reset password verification",
     *   operationId="Admin reset password verification",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"token"},
     *              @OA\Property( property="token", type="string")
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
    public function resetPasswordVerify(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string'
        ]);

        if ($validator->fails())
            return $this->sendError('Error',$validator->errors(),422);

        $admin = AdminLogin::where('forgot_password_token', $request['token'])->first();
        if(!$admin){
            return $this->sendError('Invalid token',[],400);
        }
        $admin->forgot_password_token=bin2hex(random_bytes(15));
        $admin->save();
        return response()->json('Email successfully verified', 200);
    }

    /**
     * @OA\Get(
     ** path="/api/v1/admin/admin/admin-logout",
     *   tags={"Admin"},
     *   summary="Admin Logout",
     *   operationId="Admin logout",
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
            'activity' => 'Admin Logout',
            'user_id' => Auth::user()->id
        );

        ActivityLog::createActivity($data2);

        return response([ 'message' => 'logged out successfully'],200);
    }

    /**
     * @OA\Get(
     ** path="/api/v1/admin/admin/admin-profile",
     *   tags={"Admin"},
     *   summary="Admin Profile",
     *   operationId="Admin Profile",
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
    public function adminProfile()
    {
        config(['auth.guards.api.provider' => 'admins']);
        
        //$admin = AdminLogin::find(auth()->guard('admin')->user()->id);
        $admin = AdminLogin::find(Auth::user()->id);
        if(!$admin)
        {
            return $this->sendError("Authourized user",[], 401);
        }
        
        return response()->json([$admin,'admin profile successfully retrieved'], 200);
    }

}
