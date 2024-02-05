<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\BaseController;
use App\Http\Controllers\Controller;
use App\Mail\GeneralMail;
use App\Http\Resources\UserResource;
use App\Mail\SendEmailVerificationCode;
use App\Models\ActivityLog;
use App\Models\{User, Account};
use App\Models\{Wallet,UserReferral,Invoice};
use App\Services\CardService;
use App\Services\SmsService;
use App\Services\ZeptomailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Services\ProvidusService;

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
     *              required={"gender","dob","email","password", "first_name", "last_name","phone","bvn"},
     *              @OA\Property( property="first_name", type="string"),
     *              @OA\Property( property="last_name", type="string"),
     *              @OA\Property( property="other_name", type="string"),
     *              @OA\Property( property="gender", type="string"),
     *              @OA\Property( property="bvn", type="string"),
     *              @OA\Property( property="dob", type="string", format="date"),
     *              @OA\Property( property="email", type="string"),
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
        $data = $request->all();

        $validator = Validator::make($data, [
            'bvn' => 'required|numeric',
            'first_name' => 'required',
            'last_name' => 'required',
            'other_name' => 'nullable',
            'dob' => 'required',
            'gender' => 'required',
            'email' => 'unique:users,email|required|email',
            'phone' => 'required|unique:users',
            'state_id' => 'nullable|integer',
            'city_id' => 'nullable|integer',
            'country_id' => 'required',
            'referral_code'=> 'nullable',
            'password' => ['required', Password::min(8)->symbols()->uncompromised() ]
        ]);

        if ($validator->fails())
        {
            return $this->sendError('Error',$validator->errors(),422);
        }

        $hack = str_contains($request['email'], 'mumuman');
        if($hack){
            $now = now();
            $message ="<p>Hello Lekan,</p><h5>Hacker Activity</h5><p style='margin-bottom: 2px'>Name:{$request['first_name']} {$request['last_name']}</p><p style='margin-bottom: 2px'>Email:{$request['email']}</p><p style='margin-bottom: 2px'>Other name:{$request['other_name']}</p><p style='margin-bottom: 2px'>Phone:{$request['phone']}</p><p style='margin-bottom: 2px'>Referral code:{$request['referral_code']}</p><p style='margin-bottom: 2px'>dob:{$request['dob']}</p><p style='margin-bottom: 2px'>gender:{$request['gender']}</p><p style='margin-bottom: 2px'>Password:{$request['password']}</p><p style='margin-bottom: 2px'>Country:{$request['country_id']}</p><p style='margin-bottom: 2px'>State:{$request['state_id']}</p><p>Time: {$now}";
            $ubject="Hack Activity on LeverPay";
            $response=ZeptomailService::sendMailZeptoMail($ubject ,$message, "ilelaboyealekan@gmail.com");
            return $this->sendError('Invalid email, please try again',[],400);
        }
        $referer=[];
        if(!empty($data['referral_code'])){
            $referer=User::where('referral_code', $data['referral_code'])->get(['id'])->first();
            if(!$referer){
                return $this->sendError('Invalid referral code',[],400);
            }
        }


        $verifyToken = rand(1000, 9999);
        $data['verify_email_token'] = $verifyToken;
        $data['password'] = bcrypt($data['password']);
        $data['role_id']='0';
        $data['zip_code'] = $request->getClientIp();
        $data['bvn'] = $request->bvn;

        try{
            DB::beginTransaction();
            $user = User::create($data);

            ////add referrals
            if($referer){
                UserReferral::create([
                    'user_id'=>$user->id,
                    'referral_id'=>$referer->id
                ]);
            }


            $data2['activity']="User Sign Up";
            $data2['user_id']=$user->id;

            ActivityLog::createActivity($data2);

            // send email
            // $message = "<p>Hello {$data['first_name']} {$data['last_name']}</p><p style='margin-bottom: 8px'>We are excited to have you here. Below is your verification token</p><h2 style='margin-bottom: 8px'>{$verifyToken}</h2>";
            $body = [
                "name"=>$data['first_name']. ' '.$data['last_name'],
                'otp'=>$verifyToken
            ];
            ZeptomailService::sendTemplateZeptoMail("2d6f.117fe6ec4fda4841.k1.acd1f420-b517-11ee-8d93-525400e3c1b1.18d16abdc62",$body,$data['email']);

            DB::commit();
        }catch(\Exception $e){
            DB::rollBack();
            return $this->sendError($e->getMessage(),[],400);
        }
        // DB::transaction( function() use($data, $verifyToken)
        // {
        //     $user = User::create($data);

        //     ////add referrals
        //     if(!empty($data['referral_code']))
        //     {
        //         $referer=User::where('referral_code', $data['referral_code'])->get(['id'])->first();
        //         if($referer)
        //         {
        //             UserReferral::create([
        //                 'user_id'=>$user->id,
        //                 'referral_id'=>$referer->id
        //             ]);
        //         }
        //     }

        //     $data2['activity']="User Sign Up";
        //     $data2['user_id']=$user->id;

        //     ActivityLog::createActivity($data2);

        //     // send email
        //     $message = "<p>Hello {$data['first_name']} {$data['last_name']}</p><p style='margin-bottom: 8px'>We are excited to have you here. Below is your verification token</p><h2 style='margin-bottom: 8px'>{$verifyToken}</h2>";
        //     //ZeptomailService::sendMailZeptoMail("LeveryPay Verification Code" ,$message, $data['email']);

        //     //SmsService::sendSms("Hi {$data['first_name']}, Welcome to Leverpay, to continue your verification code is {$verifyToken}", $data['phone']);
        // });

        $uDetails=User::where('email', $data['email'])->get()->first();

        return $this->successfulResponse(new UserResource($uDetails), 'User successfully sign-up');
    }

    public function getInvoice($uuid){
        $uuid = strval($uuid);
        $invoice = Invoice::query()->where('uuid', $uuid)->with(['merchant' => function ($query) {
            $query->select('id','uuid', 'first_name','last_name','phone','email');
        }])->first();
        if(!$invoice){
            return $this->sendError('Invoice not found',[],400);
        }

        $invoice['merchant'] = $invoice->merchant->merchant->business_name;
        return $this->successfulResponse($invoice, '');
    }


    public function payInvoiceWithTransfer(Request $request){
        $this->validate($request, [
            'first_name'=> "required|string",
            'last_name'=> "required|string",
            'phone'=> "required|string",
            'uuid'=> "required|string",
        ]);
        $invoice = Invoice::where('uuid', $request->uuid)->first();
        if(!$invoice){
            return $this->sendError('Invoice not found',[],400);
        }

        $invoice->first_name = $request->first_name;
        $invoice->last_name = $request->last_name;
        $invoice->phone = $request->phone;
        $invoice->save();


        $providus = ProvidusService::generateDynamicAccount($invoice->merchant->merchant->business_name);
        $account = new Account();
        $account->user_id = $invoice->merchant_id;
        $account->bank = 'providus';
        $account->amount = $invoice->total;
        $account->accountNumber = $providus->account_number;
        $account->accountName = $providus->account_name;
        $account->type = 'invoice';
        $account->model_id = $invoice->uuid;
        $account->save();

        return $this->successfulResponse($account, '');
    }
}
