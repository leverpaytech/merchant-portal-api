<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\BaseController;
use App\Http\Controllers\Controller;
use App\Mail\GeneralMail;
use App\Http\Resources\UserResource;
use App\Mail\SendEmailVerificationCode;
use App\Models\ActivityLog;
use App\Models\{Merchant,MerchantKeys,UserReferral};
use App\Models\User;
use App\Models\Wallet;
use App\Services\MerchantKeyService;
use App\Services\SmsService;
use App\Services\ZeptomailService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class AuthController extends BaseController
{
    /**
     * @OA\Get(
     ** path="/api/v1/merchant/logout",
     *   tags={"Merchant"},
     *   summary="Merchant Logout",
     *   operationId="Merchant Logout",
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
     ** path="/api/v1/merchant/signup",
     *   tags={"Merchant"},
     *   summary="Create a new merchant account",
     *   operationId="create a new merchant",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"email","password", "first_name","last_name","address", "business_name", "phone", "country_id"},
     *              @OA\Property( property="first_name", type="string"),
     *              @OA\Property( property="last_name", type="string"),
     *              @OA\Property( property="other_name", type="string"),
     *              @OA\Property( property="dob", type="string", format="date"),
     *              @OA\Property( property="email", type="string"),
     *              @OA\Property( property="address", type="string"),
     *              @OA\Property( property="business_name", type="string"),
     *              @OA\Property( property="phone", type="string"),
     *              @OA\Property( property="password", type="string"),
     *              @OA\Property( property="country_id", type="string", enum={"1"}),
     *              @OA\Property( property="state_id", type="string", enum={"1"}),
     *              @OA\Property( property="city_id", type="string", enum={"1"}),
     *              @OA\Property( property="referral_code", type="string"),
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
        $request->merge(['other_name' => null,'dob'=>null,'gender'=>null]);
        $data = $request->all();

        $validator = Validator::make($data, [
            // 'bvn'=>'required|numeric',
            'first_name' => 'required',
            'last_name' => 'required',
            'other_name' => 'nullable',
            'dob' => 'nullable',
            'address' => 'required',
            'email' => 'unique:users,email|required|email',
            'phone' => 'required|unique:users,phone',
            'business_name'=>'required|string|unique:merchants,business_name',
            'state_id' => 'nullable|integer',
            'city_id' => 'nullable|integer',
            'country_id' => 'required',
            'role_id' => 'nullable',
            'referral_code'=> 'nullable',
            'gender'=>'nullable',
            'password' => ['required', Password::min(8)->symbols()->uncompromised() ]
        ]);

        if ($validator->fails())
        {
            return $this->sendError('Error',$validator->errors(),422);
        }
        $data['bvn'] = '000000000';

        $user = $this->createMerchant($data);

        //add referrals
        if(!empty($data['referral_code']))
        {
            $referer=User::where('referral_code', $data['referral_code'])->get(['id'])->first();
            if($referer)
            {
                UserReferral::create([
                    'user_id'=>$user->id,
                    'referral_id'=>$referer->id
                ]);
            }else{
                return $this->sendError('Invalid referral code',[],400);
            }
        }

        $data2['activity']="Merchant Sign Up";
        $data2['user_id']=$user->id;

        ActivityLog::createActivity($data2);

        return $this->successfulResponse(new UserResource($user), 'Merchant successfully sign-up');
    }

    private function createMerchant($data)
    {
        $verifyToken = rand(1000,9999);

        $data['verify_email_token'] = $verifyToken;
        $data['password'] = bcrypt($data['password']);
        $data['role_id'] = '1';

        // $user = User::create($data);
        $user = new User();
        $user->bvn = '000000000';
        $user->first_name = $data['first_name'];
        $user->last_name = $data['last_name'];
        $user->other_name = $data['other_name'];
        $user->dob = $data['dob'];
        $user->gender = $data['gender'];
        $user->address = $data['address'];
        $user->email = $data['email'];
        $user->phone = $data['phone'];
        $user->verify_email_token = $data['verify_email_token'];
        $user->password = $data['password'];
        $user->country_id = $data['country_id'];
        $user->state_id = $data['state_id'];
        $user->city_id = $data['city_id'];
        $user->role_id = "1";
        $user->save();

        $merchant = new Merchant();
        $merchant->user_id = $user->id;
        $merchant->business_name = $data['business_name'];
        $merchant->save();

        // send email
        // Mail::to($data['email'])->send(new SendEmailVerificationCode($data['first_name'].' '.$data['last_name'], $verifyToken));

        // $message = "<p>Hello {$data['first_name']} {$data['last_name']},</p><p style='margin-bottom: 8px'>We are excited to have you here. Below is your verification token</p><h4 style='margin-bottom: 8px'>{$verifyToken}</h4>";

        // ZeptomailService::sendMailZeptoMail("LeveryPay Verification Code" ,$message, $data['email']);

        $body = [
            "name"=>$data['first_name']. ' '.$data['last_name'],
            'otp'=>$verifyToken
        ];
        ZeptomailService::sendTemplateZeptoMail("2d6f.117fe6ec4fda4841.k1.acd1f420-b517-11ee-8d93-525400e3c1b1.18d16abdc62",$body,$data['email']);


        SmsService::sendSms("Hi {$data['first_name']}, Welcome to Leverpay, to continue your verification code is {$verifyToken}", $data['phone']);

        return $user;
    }
}
