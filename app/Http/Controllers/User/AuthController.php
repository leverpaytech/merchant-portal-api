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
     *              required={"gender","dob","email","password", "first_name", "last_name","phone"},
     *              @OA\Property( property="first_name", type="string"),
     *              @OA\Property( property="last_name", type="string"),
     *              @OA\Property( property="other_name", type="string"),
     *              @OA\Property( property="gender", type="string"),
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
        //$nEmail="abdilkura".time()."@gmail.com";
        //User::where('email','abdilkura@gmail.com')->update(['email'=>$nEmail]);
        //User::where('phone','08136908764')->update(['phone'=>'08136908000']);
        $data = $request->all();

        $validator = Validator::make($data, [
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

        $verifyToken = rand(1000, 9999);
        $data['verify_email_token'] = $verifyToken;
        $data['password'] = bcrypt($data['password']);
        $data['role_id']='0';

        DB::transaction( function() use($data, $verifyToken)
        {
            $user = User::create($data);

            ////add referrals
            if(!empty($data['referral_code']))
            {
                $referer=User::where('referral_code', $data['referral_code'])->get(['id'])->first();
                if($referer)
                {
                    UserReferral::create([
                        'user_id'=>$user->id,
                        'referral_id'=>$referer->id
                    ]);
                }
            }

            $data2['activity']="User Sign Up";
            $data2['user_id']=$user->id;

            ActivityLog::createActivity($data2);

            // send email
            $message = "<p>Hello {$data['first_name']} {$data['last_name']}</p><p style='margin-bottom: 8px'>We are excited to have you here. Below is your verification token</p><h2 style='margin-bottom: 8px'>{$verifyToken}</h2>";
            //ZeptomailService::sendMailZeptoMail("LeveryPay Verification Code" ,$message, $data['email']);

            //SmsService::sendSms("Hi {$data['first_name']}, Welcome to Leverpay, to continue your verification code is {$verifyToken}", $data['phone']);
        });

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
