<?php

namespace App\Http\Controllers;

use App\Models\{Account, Investment,User};
use App\Models\Wallet;
use Illuminate\Http\Request;
use App\Mail\GeneralMail;
use App\Mail\SendEmailVerificationCode;
use App\Services\ProvidusService;
use App\Services\SmsService;
use Illuminate\Support\Str;
use Webpatser\Uuid\Uuid;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rules\Password;


class InvestmentController extends BaseController
{
    /**
     * @OA\Post(
     ** path="/api/v1/investment",
     *   tags={"User"},
     *   summary="Submit an investment",
     *   operationId="Submit an investment",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"gender","dob","email","password", "first_name", "last_name","phone", "amount","confirm_password"},
     *              @OA\Property( property="first_name", type="string"),
     *              @OA\Property( property="last_name", type="string"),
     *              @OA\Property( property="other_name", type="string"),
     *              @OA\Property( property="gender", type="string"),
     *              @OA\Property( property="dob", type="string", format="date"),
     *              @OA\Property( property="email", type="string"),
     *              @OA\Property( property="phone", type="string"),
     *              @OA\Property( property="password", type="string"),
     *              @OA\Property( property="password_confirmation", type="string"),
     *              @OA\Property( property="country_id", enum="[1]"),
     *              @OA\Property( property="state_id", enum="[1]"),
     *              @OA\Property( property="amount", type="string")
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
    public function submitInvestment(Request $request){
        $user = User::where('email', $request['email'])->first();
        DB::transaction( function() use($user, $request) {
            $data = $request->all();
            if(!$user){
                $data = $this->validate($request, [
                    'first_name'=>'required|string',
                    'last_name'=>'required|string',
                    'other_name'=>'nullable|string',
                    'gender'=>'required|string',
                    'dob'=>'required|string',
                    'email' => 'unique:users,email|required|email',
                    'phone'=>'required|string',
                    'country_id'=>'required|string',
                    'state_id'=>'required|string',
                    'amount'=>'required|numeric|min:1000',
                    'password'=>['required', Password::min(8)->symbols()->uncompromised(), 'confirmed' ],
                ]);
                $data['other_name'] = $request['other_name'];
                $data['password'] = bcrypt($data['password']);

                $user=User::create([
                    'first_name'=>$data['first_name'],
                    'last_name'=>$data['last_name'],
                    'other_name'=>$data['other_name'],
                    'gender'=>$data['gender'],
                    'email'=>$data['email'],
                    'phone'=>$data['phone'],
                    'country_id'=>$data['country_id'],
                    'state_id'=>$data['state_id'],
                    'password'=>$data['password'],
                    'verify_email_token'=>Str::random(5)
                ]);

                // $wallet = new Wallet();
                // $wallet->user_id = $user['id'];
                // $wallet->save();

                // DB::transaction( function() use($data, $uuid) {
                    // $user=User::create([
                    //     'first_name'=>$data['first_name'],
                    //     'last_name'=>$data['last_name'],
                    //     'other_name'=>$data['other_name'],
                    //     'gender'=>$data['gender'],
                    //     'email'=>$data['email'],
                    //     'phone'=>$data['phone'],
                    //     'country_id'=>$data['country_id'],
                    //     'state_id'=>$data['state_id'],
                    //     'password'=>$data['password']
                    // ]);

                    // $providus = ProvidusService::generateDynamicAccount("{$data['first_name']} {$data['last_name']} (Leverpay)");
                    // $invest = new Investment();
                    // $invest->uuid = $uuid;
                    // $invest->user_id = $user->id;
                    // $invest->amount = $data['amount'];
                    // if($providus['requestSuccessful']){
                    //     $invest->account_number = $providus['account_number'];
                    //     $invest->account_name = $providus['account_name'];
                    // }
                    // $invest->save();

                // });
            }
            $providus = ProvidusService::generateDynamicAccount("{$data['first_name']} {$data['last_name']} (Leverpay)");
            $invest = new Investment();
            $invest->user_id = $user->id;
            $invest->amount = $data['amount'];
            if($providus['requestSuccessful']){
                $invest->accountNumber = $providus['account_number'];
                $invest->accountName = $providus['account_name'];

                $account = new Account;
                $account->user_id = $user->id;
                $account->type='investment';
                $account->accountName = $providus['account_name'];
                $account->accountNumber = $providus['account_number'];
                $account->bank = 'providus';
                $account->save();
            }
            $invest->save();
        });
        $user = User::where('email', $request['email'])->with('investment')->first();
        if(!$user->investment){
            return $this->sendError('Error submitting investment, please try again',[],400);
        }
        return $this->successfulResponse($user,"Investment successfully created");
    }

    /*public function getInvestment()
    {
        $user = User::with('investment')->get();
        return $this->successfulResponse($user,"Investment list successfully retrieved");
    }*/
}
