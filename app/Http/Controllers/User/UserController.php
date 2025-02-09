<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\BaseController;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Resources\CardResource;
use App\Models\ActivityLog;
use App\Models\Currency;
use App\Models\{DocumentType, User,Transaction,ExchangeRate,UserBank,Kyc,UserReferral,Invoice,creptoFundingHistory};
use App\Models\{BillPaymentPin,BillPaymentHistory,Wallet,LeverPayAccountNo,VfdDiscount,ClaimedBonus};
use App\Services\{SmsService,WalletService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use App\Mail\ChangePhoneAndEmailVerifier;
use Illuminate\Validation\Rule;
use App\Services\CardService;
use App\Services\EtherscanService;
use App\Services\VfdService;
use Webpatser\Uuid\Uuid;


class UserController extends BaseController
{
    protected $userModel;

    public function __construct(User $user)
    {
        $this->userModel = $user;
    }

    /**
     * @OA\Post(
     ** path="/api/v1/user/generate-card",
     *   tags={"User"},
     *   summary="Generate new card",
     *   operationId="Add/Generate new card",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"pin"},
     *              @OA\Property( property="pin", type="string"),
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
    // public function generateCard(Request $request){

    //     if(Auth::user()->card){
    //         return $this->sendError('Card has already been created',[],400);
    //     }
    //     $card = CardService::createCard(Auth::id());
    //     return new CardResource($card);
    // }

    /**
     * @OA\Get(
     ** path="/api/v1/user/get-user-profile",
     *   tags={"User"},
     *   summary="Get user profile",
     *   operationId="get user profile details",
     *
     *
     *   @OA\Response(
     *      response=200,
     *       description="Success",
     *     ),
     *     security={
     *       {"bearer_token": {}}
     *     }
     *
     *)
     **/
    public function getUserProfile()
    {
        if(!Auth::user()->id)
            return $this->sendError('Unauthorized Access',[],401);
        $userId = Auth::user()->id;



        //active exchange rate
        $getExchageRate=ExchangeRate::where('status',1)->latest()->first();
        $rate=$getExchageRate->rate;

        $user = User::where('id', $userId)->with('wallet')->with('card')->with('currencies')->with('state')->with('city')->get()->first();

        $getV1=BillPaymentHistory::where('user_id',$userId)->where('provider_name','QUICK TELLER')->sum('cash_back');
        $user->total_cash_back= [
            'ngn'=>$getV1,
            'usdt'=>round($getV1/$rate,6)
        ];
        $getV2=Transaction::where('user_id',$userId)->where('type','debit')->sum('amount');
        $user->total_spending= [
            'ngn'=>$getV2,
            'usdt'=>round($getV2/$rate,6)
        ];
        $user->wallet->amount=[
            'ngn'=>$user->wallet->amount,
            'usdt'=>round($user->wallet->amount/$rate,6)
        ];

        $user->wallet->withdrawable_amount=[
            'ngn'=>$user->wallet->withdrawable_amount,
            'usdt'=>round($user->wallet->withdrawable_amount/$rate,6)
        ];

        $invoices = Invoice::where('user_id', $userId)
            ->with(['merchant' => function ($query) {
            $query->select('id','uuid', 'first_name','last_name','phone','email');
        }])->get();

        if(!$user)
            return $this->sendError('User not found',[],404);
        return $this->successfulResponse(new UserResource($user), 'User details successfully retrieved');
    }


    public function getDocumentType(){
        $all = DB::table('document_types')->get();
        return $this->successfulResponse($all, '');
    }



    /**
     * @OA\Post(
     ** path="/api/v1/user/search-user",
     *   tags={"User"},
     *   summary="Search User by Email",
     *   operationId="Search User by Email",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              @OA\Property( property="email", type="string"),
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
    public function searchUser(Request $request)
    {
        $data = $request->all();

        $validator = Validator::make($data, [
            'email' => 'required|email'
        ]);

        if ($validator->fails())
        {
            return $this->sendError('Error',$validator->errors(),422);
        }


        $users = User::where('email', 'LIKE','%'.$request['email'].'%')->get(['uuid','first_name','last_name','email']);
        return $this->successfulResponse($users);
    }

    /**
     * @OA\Post(
     ** path="/api/v1/user/update-user-profile",
     *   tags={"User"},
     *   summary="Update user profile",
     *   operationId="Update user profile",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              @OA\Property( property="first_name", type="string"),
     *              @OA\Property( property="last_name", type="string"),
     *              @OA\Property( property="other_name", type="string"),
     *              @OA\Property( property="other_email", type="string"),
     *              @OA\Property( property="other_phone", type="string"),
     *              @OA\Property( property="primary_email", type="string"),
     *              @OA\Property( property="primary_phone", type="string"),
     *              @OA\Property( property="gender", type="string"),
     *              @OA\Property( property="country_id", type="string", enum={"1"}),
     *              @OA\Property( property="state_id", type="string", enum={"1"}),
     *              @OA\Property( property="city_id", type="string", enum={"1"}),
     *              @OA\Property( property="passport", type="file"),
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
    public function updateUserProfile(Request $request)
    {
        $userId = Auth::user()->id;
        $email = Auth::user()->email;
        $name = Auth::user()->first_name;

        //$data = $request->all();

        $data = $this->validate($request, [
            'first_name' => 'nullable',
            'last_name' => 'nullable',
            'other_name' => 'nullable',
            'other_email' => 'nullable',
            'primary_email' => 'nullable',
            'other_phone' => 'nullable',
            'primary_phone' => 'nullable',
            'country_id' => 'nullable',
            'state_id' => 'nullable',
            'city_id' => 'nullable',
            'address' => 'nullable',
            'gender' => 'nullable',
            'passport' => 'nullable|mimes:jpeg,png,jpg,gif|max:4096'
        ]);


        if(!empty($data['passport']))
        {
            try
            {
                $newname = cloudinary()->upload($request->file('passport')->getRealPath(),
                    ['folder'=>'leverpay/profile_picture']
                )->getSecurePath();

                $data['passport']= $newname;

                //add new image
                //$request->passport->move(public_path('passports'), $newname);
            } catch (\Exception $ex) {
                return $this->sendError($ex->getMessage());
            }
        }

        if(!empty($data['primary_email']) || !empty($data['primary_phone']))
        {
            $verifyToken = rand(1000, 9999);
            $data['change_email_phone_token'] = $verifyToken;
            // send email
            // Mail::to($email)->send(new ChangePhoneAndEmailVerifier($name, $verifyToken));

            $html = "<p style='margin-bottom: 8px'>
                        Below is your verification token
                    </p>
                    <h4 style='margin-bottom: 8px'>
                        {$verifyToken}
                    </h4>
                ";

            SmsService::sendMail("Dear {$name},", $html, "Verification Code", $email);
        }

        $user = $this->userModel->updateUser($data,$userId);
        // $user->firstname
        $data2['activity']="User Profile Update";
        $data2['user_id']=$userId;

        ActivityLog::createActivity($data2);


        return $this->successfulResponse(new UserResource($user), 'User profile successfully updated');
    }

    /**
     * @OA\Get(
     ** path="/api/v1/user/get-user-currencies",
     *   tags={"Merchant"},
     *   summary="Get user's currencies",
     *   operationId="get user's currencies",
     *
     *   @OA\Response(
     *      response=200,
     *       description="Success",
     *     ),
     *     security={
     *       {"bearer_token": {}}
     *     }
     *
     *)
     **/
    public function getUserCurrencies(){
        return $this->successfulResponse(Auth::user()->currencies,'');
    }

    /**
     * @OA\Post(
     ** path="/api/v1/user/add-currencies",
     *   tags={"User"},
     *   summary="Add user curriencies",
     *   operationId="Add user curriencies",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"curriencies"},
     *              @OA\Property( property="curriencies", type="array",@OA\Items( type="integer"), )
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
     *   ),
     *   security={
     *       {"bearer_token": {}}
     *   }
     *)
     **/
    public function addCurrencies(Request $request)
    {
        $this->validate($request, [
            'currencies'=>'required|array'
        ]);
        $user = Auth::user()->currencies()->sync($request['currencies']);
        return $this->successfulResponse(Auth::user()->currencies,'');
    }

    public function getCurrencies(){
        $currencies = Currency::where('status', 1)->get();
        return $this->successfulResponse($currencies,'');
    }

    /**
     * @OA\Post(
     ** path="/api/v1/user/add-bank-account",
     *   tags={"User"},
     *   summary="Add bank account",
     *   operationId="Add bank account",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"bank_id","account_no"},
     *              @OA\Property( property="account_no", type="string"),
     *              @OA\Property( property="bank_id", type="string", enum={"1"}),
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
     *   ),
     *   security={
     *       {"bearer_token": {}}
     *   }
     *)
     **/
    public function addBankAccount(Request $request)
    {
        $data = $request->all();

        $validator = Validator::make($data, [
            'account_no'=>'unique:user_banks,account_no|required',
            'account_name'=> 'required|string',
            'bank_id'=>'required|string'
        ]);

        if ($validator->fails())
        {
            return $this->sendError('Error',$validator->errors(),422);
        }

        $userBank = new UserBank();
        $userBank->user_id = Auth::user()->id;
        $userBank->bank_id  = $data['bank_id'];
        $userBank->account_no  = $data['account_no'];
        $userBank->account_name  = $data['account_name'];
        $userBank->save();

        return $this->successfulResponse($userBank,'Account successfully added');
    }

    /**
     * @OA\Get(
     ** path="/api/v1/user/get-user-bank-account",
     *   tags={"User"},
     *   summary="Get user bank account",
     *   operationId="get user bank account",
     *
     *   @OA\Response(
     *      response=200,
     *       description="Success",
     *     ),
     *     security={
     *       {"bearer_token": {}}
     *     }
     *
     *)
     **/
    public function getUserBankAccount()
    {
        $user_id=Auth::user()->id;

        $userBanks=UserBank::join('banks','user_banks.bank_id','=','banks.id')
            ->join('users', 'user_banks.user_id','=','users.id')
            ->where('user_banks.user_id', $user_id)
            ->get([
                'users.first_name',
                'users.last_name',
                'users.other_name',
                'user_banks.account_no',
                'banks.name'
            ]);
        return $this->successfulResponse($userBanks,'Bak List');
    }

    /*public function generateAccNo()
    {
        $authurl= config('services.vfd.authurl');
        $secretkey= config('services.vfd.consumerSecretkey');
        $baseurl= config('services.vfd.baseurl');
        $consumerkey= config('services.vfd.consumerkey');
        $accessToken= config('services.vfd.accessToken');
        $wallet_credentials= config('services.vfd.walletCredentials');
        $onBoardUrl= config('services.vfd.onboarding');
        $vfdBankAuth="VFDBankAuth";

        return $access_token=$this->getToken($authurl, $wallet_credentials);

        if(isset($access_token))
        {
            $requestData=array(
                "firstname"=>"Jonatham",
                "lastname"=>"Goodluck",
                "middlename"=>"Becky",
                "dob"=>"04 October 1960",
                "address"=>"No 5 address",
                "gender"=>"Male",
                "phone"=>"08136908764",
                "bvn"=>"22222222223"
            );

            $postData= json_encode($requestData);
            $ch1 = curl_init();
            curl_setopt_array($ch1, array(
                CURLOPT_URL => $baseurl."client/individual",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => false,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $postData,
                CURLOPT_HTTPHEADER => array(
                    "Content-Type: application/json",
                    "Authorization: Bearer".$access_token
                    )
                ));
            $acc_responses = curl_exec($ch1);
            curl_close($ch1);

            $acc_responses = json_decode($acc_responses);

            return $acc_responses;
        }
    }*/

    /*private function getToken($authurl,$wallet_credentials)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
        CURLOPT_URL => $authurl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYHOST =>false,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => "grant_type=client_credentials",
        CURLOPT_HTTPHEADER => array(
            "Content-Type: application/x-www-form-urlencoded",
            "Authorization: Bearer ".$wallet_credentials
        ),
        ));
        $response = curl_exec($curl);
        //curl_close($curl);
        $no_json = json_decode($response, true);

        if (!curl_errno($curl)) {

            switch ($http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE)) {
                case 200:
                    return $no_json['access_token'];
                    break;
                default:
                    return $no_json;
            }
        }
        else{
            return false;
        }

        curl_close($curl);

    }*/

    /*public function onBoarding()
    {
        $requestData=array(
            "username"=>"leverpay",
            "walletName"=>"leverpayWallet",
            "webhookUrl"=>"",
            "shortName"=>"LPCT",
            "implementation"=>"Pool"
        );
        $postData = json_encode($requestData);

        $accessToken= config('services.vfd.accessToken');
        $onBoardUrl= config('services.vfd.onboarding');

        $ch1 = curl_init();

        curl_setopt($ch1, CURLOPT_URL, $onBoardUrl);
        curl_setopt($ch1, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch1, CURLOPT_HEADER, FALSE);
        curl_setopt($ch1, CURLOPT_POST, TRUE);
        curl_setopt($ch1, CURLOPT_FAILONERROR, TRUE);
        curl_setopt($ch1, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch1, CURLOPT_HTTPHEADER, array(
                   "Content-Type: application/json",
                   "Authorization: Bearer ".$accessToken)
             );

        $acc_responses = curl_exec($ch1);
        curl_close($ch1);

        //return $http_status = curl_getinfo($ch1, CURLINFO_HTTP_CODE);
        //return $curl_errno=curl_errno($ch1);

        $acc_responses = json_decode($acc_responses);
        if (curl_exec($ch1) === false)
        {
            return curl_error($ch1);
        } else {
            return $acc_responses;
        }

        //return curl_error($ch1);

    }*/

    /**
     * @OA\Post(
     ** path="/api/v1/user/upgrade-to-gold-card-kyc",
     *   tags={"User"},
     *   summary="Upgarte to gold card kyc",
     *   operationId="Upgarte to gold card kyc",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"document_type_id","country_id","state_id","residential_address","passport","id_card_front","bvn","nin","place_of_birth"},
     *              @OA\Property( property="passport", type="file"),
     *              @OA\Property( property="document_type_id", type="string", enum={"1"}),
     *              @OA\Property( property="id_card_front", type="file"),
     *              @OA\Property( property="id_card_back", type="file"),
     *              @OA\Property( property="bvn", type="string"),
     *              @OA\Property( property="nin", type="string"),
     *              @OA\Property( property="country_id", type="string", enum={"1"}),
     *              @OA\Property( property="state_id", type="string", enum={"1"}),
     *              @OA\Property( property="place_of_birth", type="string"),
     *              @OA\Property( property="residential_address", type="string"),
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
    public function goldUpgradeKyc(Request $request)
    {
        $data = $request->all();

        $validator = Validator::make($data, [
            'passport' => 'required|mimes:jpeg,png,jpg|max:4096',
            'document_type_id' => 'required',
            'id_card_front' => 'required|mimes:jpeg,png,jpg|max:4096',
            'id_card_back' => 'nullable|mimes:jpeg,png,jpg|max:4096',
            'country_id' => 'required',
            'state_id' => 'required',
            'residential_address' => 'required',
            'bvn' => 'required|numeric',
            'nin' => 'required|numeric',
            'place_of_birth' => 'required'
        ],[
            'document_type_id.required' => 'Document type is required',
            'country_id.required' => 'Country is required',
            'state_id.required' => 'State is required',
        ]);

        if ($validator->fails())
        {
            return $this->sendError('Error',$validator->errors(),422);
        }

        $user_id=Auth::user()->id;

        $data['user_id']=$user_id;
        $data['card_type']=2; //gold

        try{
            $passport = cloudinary()->upload($request->file('passport')->getRealPath(),
                ['folder'=>'leverpay/kyc']
            )->getSecurePath();
            $data['passport']=$passport;


            $idFront = cloudinary()->upload($request->file('id_card_front')->getRealPath(),
                ['folder'=>'leverpay/kyc']
            )->getSecurePath();
            $data['id_card_front']=$idFront;

            if($request->hasFile('id_card_back'))
            {
                $idBack = cloudinary()->upload($request->file('id_card_back')->getRealPath(),
                ['folder'=>'leverpay/kyc']
                )->getSecurePath();
                $data['id_card_back']=$idBack;
            }
        } catch (\Exception $ex) {
            return $this->sendError($ex->getMessage());
        }

        $user=Kyc::create($data);

        if (!$user)
        {
            return $this->sendError('Error',$user,422);
        }

        $data2['activity']="User kyc for gold card upgrade";
        $data2['user_id']=$user_id;

        ActivityLog::createActivity($data2);

        $response = [
            'success' => true,
            'user' =>$user,
            'message' => "Gold card kyc upgarde successfully sent"
        ];

        return response()->json($response, 200);
    }

    /**
     * @OA\Get(
     ** path="/api/v1/user/gold-kyc-upgrade-details",
     *   tags={"User"},
     *   summary="Get user gold card kyc details",
     *   operationId="Get user gold card kyc details",
     *
     *   @OA\Response(
     *      response=200,
     *       description="Success",
     *     ),
     *     security={
     *       {"bearer_token": {}}
     *     }
     *
     *)
     **/
    public function goldKycUpgradeDetails()
    {
        $user_id=Auth::user()->id;
        $kycs=Kyc::join('countries','countries.id','=','kycs.country_id')
        ->join('states','states.id','=','kycs.state_id')
        ->join('document_types','document_types.id','=','kycs.document_type_id')
            ->where('user_id', $user_id)
            ->where('card_type', 1)
            ->get([
                'kycs.passport',
                'document_types.name',
                'kycs.id_card_front',
                'kycs.id_card_back',
                'countries.country_name',
                'states.state_name',
                'kycs.residential_address',
                'kycs.bvn',
                'kycs.nin',
                'kycs.place_of_birth',
                'kycs.status',
            ]);

        return $this->successfulResponse($kycs, 'gold card kyc details successfully retrieved');

    }

    /**
     * @OA\Post(
     ** path="/api/v1/user/upgrade-to-diamond-card-kyc",
     *   tags={"User"},
     *   summary="Upgarte to diamond card kyc",
     *   operationId="Upgarte to diamond card kyc",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"document_type_id","id_card_front","utility_bill"},
     *              @OA\Property( property="document_type_id", type="string", enum={"1"}),
     *              @OA\Property( property="id_card_front", type="file"),
     *              @OA\Property( property="id_card_back", type="file"),
     *              @OA\Property( property="utility_bill", type="file")
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
    public function diamondUpgradeKyc(Request $request)
    {
        $data = $request->all();

        $validator = Validator::make($data, [
            'document_type_id' => 'required',
            'id_card_front' => 'required|mimes:jpeg,png,jpg|max:4096',
            'id_card_back' => 'nullable|mimes:jpeg,png,jpg|max:4096',
            'utility_bill' => 'required|mimes:jpeg,png,jpg|max:4096'
        ],[
            'document_type_id.required' => 'Document type is required'
        ]);

        if ($validator->fails())
        {
            return $this->sendError('Error',$validator->errors(),422);
        }

        $user_id=Auth::user()->id;

        $data['user_id']=$user_id;
        $data['card_type']=3; //diamond

        try{
            $utility_bill = cloudinary()->upload($request->file('utility_bill')->getRealPath(),
                ['folder'=>'leverpay/kyc']
            )->getSecurePath();
            $data['utility_bill']=$utility_bill;


            $idFront = cloudinary()->upload($request->file('id_card_front')->getRealPath(),
                ['folder'=>'leverpay/kyc']
            )->getSecurePath();
            $data['id_card_front']=$idFront;

            if($request->hasFile('id_card_back'))
            {
                $idBack = cloudinary()->upload($request->file('id_card_back')->getRealPath(),
                ['folder'=>'leverpay/kyc']
                )->getSecurePath();
                $data['id_card_back']=$idBack;
            }
        } catch (\Exception $ex) {
            return $this->sendError($ex->getMessage());
        }

        $user=Kyc::create($data);

        $data2['activity']="User kyc for diamond card upgrade";
        $data2['user_id']=$user_id;

        ActivityLog::createActivity($data2);

        $response = [
            'success' => true,
            'user' =>$user,
            'message' => "Diamond card kyc upgarde successfully sent"
        ];

        return response()->json($response, 200);
    }

    /**
     * @OA\Get(
     ** path="/api/v1/user/diamond-kyc-upgrade-details",
     *   tags={"User"},
     *   summary="Get user diamond card kyc details",
     *   operationId="Get user diamond card kyc details",
     *
     *   @OA\Response(
     *      response=200,
     *       description="Success",
     *     ),
     *     security={
     *       {"bearer_token": {}}
     *     }
     *
     *)
     **/
    public function diamondKycUpgradeDetails()
    {
        $user_id=Auth::user()->id;
        $kycs=Kyc::join('document_types','document_types.id','=','kycs.document_type_id')
            ->where('user_id', $user_id)
            ->where('card_type', 2)
            ->orderByDesc('kycs.created_at')
            ->limit(1)
            ->get([
                'kycs.utility_bill',
                'document_types.name',
                'kycs.id_card_front',
                'kycs.id_card_back',
                'kycs.status'
            ]);

        return $this->successfulResponse($kycs, 'diamond card kyc details successfully retrieved');

    }

    /**
     * @OA\Post(
     ** path="/api/v1/user/upgrade-to-enterprise-card-kyc",
     *   tags={"User"},
     *   summary="Upgarte to enterprise card kyc",
     *   operationId="Upgarte to enterprise card kyc",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"business_address","business_certificate","rc_no"},
     *              @OA\Property( property="business_certificate", type="file"),
     *              @OA\Property( property="business_address", type="string"),
     *              @OA\Property( property="rc_no", type="string")
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
    public function enterpriseUpgradeKyc(Request $request)
    {
        $data = $request->all();

        $validator = Validator::make($data, [
            'business_address' => 'required',
            'rc_number' => 'required',
            'business_certificate' => 'required|mimes:jpeg,png,jpg|max:4096'
        ]);

        if ($validator->fails())
        {
            return $this->sendError('Error',$validator->errors(),422);
        }

        $user_id=Auth::user()->id;

        $data['user_id']=$user_id;
        $data['card_type']=5; //Enterprice

        try{
            $business_certificate = cloudinary()->upload($request->file('business_certificate')->getRealPath(),
                ['folder'=>'leverpay/kyc']
            )->getSecurePath();
            $data['business_certificate']=$business_certificate;
        } catch (\Exception $ex) {
            return $this->sendError($ex->getMessage());
        }

        $user=Kyc::create($data);

        $data2['activity']="User kyc for enterprise card upgrade";
        $data2['user_id']=$user_id;

        ActivityLog::createActivity($data2);

        $response = [
            'success' => true,
            'user' =>$user,
            'message' => "Enterprise card kyc upgarde successfully sent"
        ];

        return response()->json($response, 200);
    }

    /**
     * @OA\Get(
     ** path="/api/v1/user/enterprise-kyc-upgrade-details",
     *   tags={"User"},
     *   summary="Get user enterprise card kyc details",
     *   operationId="Get user enterprise card kyc details",
     *
     *   @OA\Response(
     *      response=200,
     *       description="Success",
     *     ),
     *     security={
     *       {"bearer_token": {}}
     *     }
     *
     *)
     **/
    public function enterpriseKycUpgradeDetails()
    {
        $user_id=Auth::user()->id;
        $kycs=Kyc::where('user_id', $user_id)
            ->where('card_type', 4)
            ->get([
                'kycs.business_certificate',
                'kycs.business_address',
                'kycs.rc_number',
                'kycs.status'
            ]);

        return $this->successfulResponse($kycs, 'enterprise card kyc details successfully retrieved');

    }

    /**
     * @OA\Get(
     ** path="/api/v1/user/get-exchange-rates",
     *   tags={"User"},
     *   summary="Get exchange rate",
     *   operationId="Get exchange rate",
     *
     *   @OA\Response(
     *      response=200,
     *       description="Success",
     *     ),
     *     security={
     *       {"bearer_token": {}}
     *     }
     *
     *)
     **/
    public function getExchangeRates()
    {
        $rates = ExchangeRate::latest()->first();
        return $this->successfulResponse($rates, '');
    }

    /**
     * @OA\Get(
     ** path="/api/v1/user/get-referral-code",
     *   tags={"User"},
     *   summary="Get referral code",
     *   operationId="Get referral code",
     *
     *   @OA\Response(
     *      response=200,
     *       description="Success",
     *     ),
     *     security={
     *       {"bearer_token": {}}
     *     }
     *
     *)
     **/
    public function getReferralCode()
    {
        if(!Auth::user()->id)
            return $this->sendError('Unauthorized Access',[],401);
        $userId = Auth::user()->id;

        $user=User::where('id', $userId)->get(['referral_code'])->first();
        $countRef=UserReferral::where('referral_id', $userId)->count();

        $referrals = UserReferral::where('referral_id', $userId)->get();
        
        $totalBonus = 0;
        foreach ($referrals as $referral) 
        {
            $deposit = $referral->user->transactions()->where('type', 'credit')->sum('amount'); 
            
            if ($deposit >= 1000) {
                $totalBonus += 100;
            } elseif ($deposit >= 500) {
                $totalBonus += 50;
            }
        }
        
        $claimedBonus=ClaimedBonus::where('user_id', $userId)->sum('amount');

        $result=[
            'referral_code'=>$user->referral_code,
            'total_point'=>($countRef*5)+5,  //5point is added for newly signup user
            'referral_bonus' => ($totalBonus-$claimedBonus)
        ];

        return response()->json($result, 200);
    }

    /**
     * @OA\Get(
     ** path="/api/v1/user/get-referrals",
     *   tags={"User"},
     *   summary="Get referrals",
     *   operationId="Get referrals",
     *
     *   @OA\Response(
     *      response=200,
     *       description="Success",
     *     ),
     *     security={
     *       {"bearer_token": {}}
     *     }
     *
     *)
     **/
    public function getReferrals()
    {
        if(!Auth::user()->id)
            return $this->sendError('Unauthorized Access',[],401);
        $userId = Auth::user()->id;

        $referrals=UserReferral::join('users', 'users.id', '=', 'user_referrals.user_id')
            ->where('user_referrals.referral_id', $userId)
            ->orderBy('user_referrals.updated_at', 'DESC')
            ->get([
                'user_referrals.created_at',
                'users.first_name',
                'users.last_name',
                'users.phone',
                'users.email',
                'users.role_id as role'
            ]);

        $referrals->transform(function ($referral)
        {
            $referral->role=($referral->role==1?'Merchant':'User');
            return $referral;
        });

        return $this->successfulResponse($referrals, 'referrals successfully retrieved');
    }

    /**
     * @OA\Post(
     ** path="/api/v1/user/etherscan/validate-transaction",
     *   tags={"User"},
     *   summary="Validate crepto funding transaction",
     *   operationId="Validate crepto funding transaction",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"transaction_hash","amount"},
     *              @OA\Property( property="transaction_hash", type="string"),
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
    public function fundWalletWithCrepto(Request $request)
    {
        $data = $request->all();

        $validator = Validator::make($data, [
            'transaction_hash' => 'required|string',
            'amount' => 'required|numeric'
        ]);

        if ($validator->fails())
        {
            return $this->sendError('Error',$validator->errors(),422);
        }

        if(!Auth::user()->id)
            return $this->sendError('Unauthorized Access',[],401);
        $userId = Auth::user()->id;

        $transactionHash = $data['transaction_hash'];
        $amount = $data['amount'];

        $checkIfExist=creptoFundingHistory::where('transaction_hash', $transactionHash)->get(['id'])->first();

        if($checkIfExist->id)
        {
            return $this->sendError('Invalid Transaction hash',[],402);
        }
        //$apiUrl = config('services.etherscan.api_url');
        //$apiKey = config('services.etherscan.api_key');
        //$response=EtherscanService::getTransactionDetails($address,$apiUrl,$apiKey);

        $response=EtherscanService::getTransactionDetails($transactionHash);


        if(isset($response['amount']) && $response['amount'] !=0)
        {
            if($response['amount']==$amount)
            {
                $result=[
                    'actual_transaction_amount_found'=>$amount,
                    'sender_address'=>$response['sender'],
                    'reciever_address'=>$response['reciever'],
                    'message'=>"transaction with $".$amount." successfully found on etherscan"
                ];
            }
            else{
                $result=[
                    'actual_transaction_amount_found'=>$response['amount'],
                    'sender_address'=>$response['sender'],
                    'reciever_address'=>$response['reciever'],
                    'message'=>"transaction found on etherscan, but the amount provided does not correspond with the one found on etherscan"
                ];
            }
            DB::transaction(function () use($userId, $transactionHash, $amount){
                creptoFundingHistory::create([
                    'user_id'=>$userId,
                    'uuid'=> Uuid::generate()->string,
                    'transaction_hash'=>$transactionHash,
                    'amount'=>$amount
                ]);
                
                TopupRequest::create([
                    'user_id'=>$userId,
                    'reference'=>$transactionHash,
                    'amount'=>$amount,
                    'image_url'=>'Nil'
                ]);
            });

        }else{
            $result=$response;
        }


        return response()->json($result, 200);

    }

    /**
     * @OA\Get(
     ** path="/api/v1/user/vfd/check-transaction/{reference_no}",
     *   tags={"VFD Bill Payment"},
     *   summary="Check transaction by reference no",
     *   operationId="Check transaction by reference no",
     *
     * * @OA\Parameter(
     *      name="reference_no",
     *      in="path",
     *      required=true,
     *      @OA\Schema(
     *           type="string",
     *      )
     *   ),
     *
     *   @OA\Response(
     *      response=200,
     *       description="Success",
     *     ),
     *     security={
     *       {"bearer_token": {}}
     *     }
     *
     *)
     **/
    public function checkTransaction($reference_no)
    {
        if(!Auth::user()->id)
            return $this->sendError('Unauthorized Access',[],401);
        $userId = Auth::user()->id;

        $response=VfdService::generateAccessToken();
        $response=json_decode($response);
        $accessToken=$response->data->access_token;

        $response=VfdService::checkTransaction($accessToken,$reference_no);
        $response=json_decode($response);


        return $response;
    }

    /**
     * @OA\Get(
     ** path="/api/v1/user/vfd/get-biller-categories",
     *   tags={"VFD Bill Payment"},
     *   summary="Get Biller Categories",
     *   operationId="Get Biller Categories",
     *
     *   @OA\Response(
     *      response=200,
     *       description="Success",
     *     ),
     *     security={
     *       {"bearer_token": {}}
     *     }
     *
     *)
     **/
    public function billerCategories()
    {
        if(!Auth::user()->id)
            return $this->sendError('Unauthorized Access',[],401);
        $userId = Auth::user()->id;

        $response=VfdService::generateAccessToken();
        $response=json_decode($response);
        $accessToken=$response->data->access_token;

        $getCategories=VfdService::getBillerCategory($accessToken);
        $billCategories=json_decode($getCategories);

        return $billCategories;
    }

    /**
     * @OA\Get(
     ** path="/api/v1/user/vfd/get-biller-list/{categoryName}",
     *   tags={"VFD Bill Payment"},
     *   summary="Get Biller List",
     *   operationId="Get Biller List",
     *
     * * @OA\Parameter(
     *      name="categoryName",
     *      in="path",
     *      required=true,
     *      @OA\Schema(
     *           type="string",
     *      )
     *   ),
     *
     *   @OA\Response(
     *      response=200,
     *       description="Success",
     *     ),
     *     security={
     *       {"bearer_token": {}}
     *     }
     *
     *)
     **/
    public function billerList($categoryName)
    {
        if(!Auth::user()->id)
            return $this->sendError('Unauthorized Access',[],401);
        $userId = Auth::user()->id;

        $response=VfdService::generateAccessToken();
        $response=json_decode($response);
        $accessToken=$response->data->access_token;

        //return $categoryName;
        $getBillCategories=VfdService::getBillerCategoryList($accessToken,$categoryName);
        $getBillerList=json_decode($getBillCategories);


        return $getBillerList;
    }

    /**
     * @OA\Get(
     ** path="/api/v1/user/vfd/get-biller-items/{billerId}/{divisionId}/{productId}",
     *   tags={"VFD Bill Payment"},
     *   summary="Get Biller Items",
     *   operationId="Get Biller Items",
     *
     * * @OA\Parameter(
     *      name="billerId",
     *      in="path",
     *      required=true,
     *      description="This is returned from biller List",
     *      @OA\Schema(
     *           type="string"
     *      )
     *   ),
     * * @OA\Parameter(
     *      name="divisionId",
     *      in="path",
     *      required=true,
    *       description="This is returned from biller List",
     *      @OA\Schema(
     *           type="string"
     *      )
     *   ),
     *
     * @OA\Parameter(
     *     name="productId",
     *     in="path",
     *     required=true,
     *     description="This is returned from biller List",
     *     @OA\Schema(
     *         type="string"
     *     )
     * ),
     *
     *
     *   @OA\Response(
     *      response=200,
     *       description="Success",
     *     ),
     *     security={
     *       {"bearer_token": {}}
     *     }
     *
     *)
     **/
    public function billerItems($billerId,$divisionId,$productId)
    {
        if(!Auth::user()->id)
            return $this->sendError('Unauthorized Access',[],401);
        $userId = Auth::user()->id;

        $response=VfdService::generateAccessToken();
        $response=json_decode($response);
        $accessToken=$response->data->access_token;


        $getItems=VfdService::getBillerItems($accessToken,$billerId,$divisionId,$productId);
        $geBillerItems=json_decode($getItems);

        //return $geBillerItems;
        /*
            this was temporary done to resolve the issue electricity bills purchase failure
            it will remove once VFD make update from their end
        */
        $categoryid=$geBillerItems->data->paymentitems[0]->categoryid;
        $billerid=$geBillerItems->data->paymentitems[0]->billerid;
        if($categoryid==2 || $billerid=='GLO_VBANK' || $billerid=='GLO_VBANK_DATA')
        {
            $geBillerItems->data->paymentitems[0]->paymentitemname=$geBillerItems->data->paymentitems[0]->paymentCode;
        }
        $geBillerItems->reference_no=base64_encode("Leverpay-".uniqid());

        return $geBillerItems;
    }

    /**
     * @OA\Get(
     *     path="/api/v1/user/vfd/validate-customer",
     *     tags={"VFD Bill Payment"},
     *     summary="Validate Customer",
     *     operationId="validateCustomer",
     *
     *     @OA\Parameter(
     *         name="divisionId",
     *         in="query",
     *         required=true,
     *         description="This is returned from biller List as division",
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Parameter(
     *         name="paymentItem",
     *         in="query",
     *         required=true,
     *         description="This is returned from biller items as paymentCode",
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Parameter(
     *         name="customerId",
     *         in="query",
     *         required=true,
     *         description="Customer Id i.e Meter Token",
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Parameter(
     *         name="billerId",
     *         in="query",
     *         required=true,
     *         description="This signifies the ID of the biller it is returned from the Biller List",
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Success"
     *     ),
     *
     *     security={
     *         {"bearer_token": {}}
     *     }
     * )
    **/

    public function validateCustomer(Request $request)
    {
        if (!Auth::user()->id) {
            return $this->sendError('Unauthorized Access', [], 401);
        }

        $userId = Auth::user()->id;


        $divisionId = $request->query('divisionId');
        $paymentItem = $request->query('paymentItem');
        $customerId = $request->query('customerId');
        $billerId = $request->query('billerId');


        $response = VfdService::generateAccessToken();
        $response = json_decode($response);
        $accessToken = $response->data->access_token;

        return $getDD = VfdService::validateCustomer($accessToken, $divisionId, $paymentItem, $customerId, $billerId);
        // $response = json_decode($getDD);
        // return $response;
    }


    /**
     * @OA\Post(
     ** path="/api/v1/user/vfd/submit-bill-payment",
     *   tags={"VFD Bill Payment"},
     *   summary="Submit Bill Payment",
     *   operationId="Submit Bill Payment",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"customerId","amount","division","paymentItem","productId","billerId","pin","reference_no"},
     *              @OA\Property( property="customerId", type="string", description="e.g Phone Number or Meter Token"),
     *              @OA\Property( property="amount", type="string", description="amount to acquire service"),
     *              @OA\Property( property="division", type="string", description="This is returned from biller List and it should be hidden"),
     *              @OA\Property( property="paymentItem", type="string", description="This is returned from biller items and it should be hidden"),
     *              @OA\Property( property="productId", type="string", description="This is returned from biller List and it should be hidden"),
     *              @OA\Property( property="billerId", type="string", description="This signifies the ID of the biller it is returned from the Biller List and it should be hidden"),
     *              @OA\Property( property="pin", type="string", description="bill payment pin"),
     *              @OA\Property( property="reference_no", type="string", description="transaction reference no"),
     *              @OA\Property( property="email", type="string", description="enter email if any"),
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
    public function billPayment(Request $request)
    {

        $data = $request->all();

        $validator = Validator::make($data, [
            'customerId' => 'required|string',
            'amount' => 'required|numeric',
            'division' => 'required|string',
            'paymentItem' => 'required',
            'productId' => 'required',
            'billerId' => 'required',
            'pin' => 'required|numeric',
            'reference_no' => 'required',
            'email' => 'nullable'
        ]);

        if ($validator->fails())
        {
            return $this->sendError('Error',$validator->errors(),422);
        }

        $user = Auth::user();
        if(!$user->id)
            return $this->sendError('Unauthorized Access',[],401);

        $userId = $user->id;

        // $excCshBk=BillPaymentHistory::where('user_id', $userId)->get();
        // return response()->json($excCshBk, 422);

        $checkRefNo = $this->checkReferenceNoValidity($userId, $data['reference_no']);
        if ($checkRefNo) {
            return response()->json('Duplicate Transactions', 422);
        }

        $accessToken = json_decode(VfdService::generateAccessToken())->data->access_token;
        if(!$accessToken)
        {
            return response()->json('Invalid access token', 422);
        }

        $getCashBack=VfdDiscount::where('biller_id', $data['billerId'])->get(['percent'])->first();
        $cashBack=0.00;
        if($getCashBack)
        {
            $cashBack=($getCashBack->percent/100)*$data['amount'];
        }

        $nin=[
            //'referenceNo'=>"Leverpay-".uniqid(),
            'referenceNo'=>base64_decode($data['reference_no']),
            'customerId'=>$data['customerId'],
            'amount'=>$data['amount'],
            'cash_back'=>$cashBack,
            'division'=>$data['division'],
            'paymentItem'=>$data['paymentItem'],
            'productId'=>$data['productId'],
            'billerId'=>$data['billerId']
        ];

        $checkPin = $this->checkPinValidity($userId, $data['pin']);
        if (!$checkPin) {
            return response()->json('Invalid pin', 422);
        }

        $checkBalance = $this->checkWalletBalance($userId, $data['amount']);
        if (!$checkBalance) {
            return response()->json('Insufficient wallet balance', 422);
        }

        $getLeverPayAccount = $this->getLeverPayAccount();

        if (!$getLeverPayAccount->balance) {
            return response()->json('Transaction Failed, Add at least one leverpay account', 422);
        }

        $newBalance=($getLeverPayAccount->balance + $data['amount'])-$cashBack;

        $payBillResult =VfdService::payBill($accessToken, $data['customerId'], $data['amount'], $data['division'], $data['paymentItem'], $data['productId'], $data['billerId'], $nin['referenceNo']);

        $payBillResult=json_decode($payBillResult);

        if ($payBillResult->status != '00') {
            return response()->json('Transaction Failed, '.$payBillResult->message, 422);
        }
        $payToken=isset($payBillResult->token)?$payBillResult->token:'';
        $nin['token']=$payToken;
        // Start the database transaction
        DB::beginTransaction();
        try{
            $this->performTransaction($userId, $nin, $newBalance, $cashBack);
            DB::commit();
            if($cashBack > 0)
            {
                $msg="Your transaction was successful and Leverpay has given you a  (Cashback Reward of: #".number_format($cashBack,2).")";
            }
            else{
                $msg="Your transaction was successful";
            }
            $result = [
                'message'=>$msg,
                'reference' => $nin['referenceNo'],
                'product' => $data['paymentItem'],
                'cashback'=>$cashBack,
                'token'=>$payToken
            ];
            return response()->json($result, 200);
        }catch (\Exception $e) {
            DB::rollBack();
            //throw $e;
            return response()->json('Transaction Failed ', 422);
        }
    }

    protected function checkPinValidity($userId, $pin)
    {
        return BillPaymentPin::where('user_id', $userId)->where('pin', $pin)->first();
    }

    protected function checkReferenceNoValidity($reference_no)
    {
        $refNo=base64_decode($reference_no);
        return BillPaymentHistory::where('transaction_reference', $refNo)->first();
    }

    protected function checkWalletBalance($userId, $amount)
    {
        $checkBalance = Wallet::where('user_id', $userId)->first(['withdrawable_amount', 'amount']);

        return $checkBalance && $checkBalance->withdrawable_amount >= $amount;
    }

    protected function getLeverPayAccount()
    {
        return DB::table('lever_pay_account_no')->where('id', 2)->first();
    }

    protected function updateLeverPayAccountBalance($amount, $currentBalance)
    {
        $newBalance = $currentBalance + $amount;
        DB::table('lever_pay_account_no')->where('id', 2)->update(['balance' => $newBalance]);

        return $newBalance;
    }

    protected function performTransaction($userId, $nin, $newBalance,$cashBack)
    {
        //$userId=$user->id;
        $getOldBal=Wallet::where('user_id', $userId)->get(['withdrawable_amount', 'amount'])->first();

        $extra=json_encode($nin);
        $wBal=$nin['amount']-$cashBack;
        $new_user_wall=$getOldBal->withdrawable_amount-$wBal;
        WalletService::subtractFromWallet($userId, $wBal, 'naira');

        DB::table('lever_pay_account_no')->where('id', 2)->update(['balance' => $newBalance]);

        BillPaymentHistory::create([
            'user_id' => $userId,
            'customerId' => $nin['customerId'],
            'unit_purchased' => 0,
            'price' => $nin['amount'],
            'amount' => $nin['amount'],
            'cash_back'=>$cashBack,
            'category' => $nin['division'],
            'biller' => $nin['billerId'],
            'product' => $nin['productId'],
            'item' => $nin['paymentItem'],
            'extra' => $extra,
            'provider_name' => 'VFD',
            'transaction_reference' => $nin['referenceNo'],
        ]);

        $details = json_encode([
            "bill_phone"=>$nin['customerId'],
            "bill_id"=>$nin['billerId'],
            "data_id"=>$nin['paymentItem'],
            "bill_provider"=>"vfd bank",
            "token"=>$nin['token']
        ]);

        Transaction::create([
            'user_id' =>  $userId,
            'reference_no' => $nin['referenceNo'],
            'tnx_reference_no' => $nin['referenceNo'],
            'amount' => $nin['amount'],
            'balance' => $new_user_wall,
            'type' => 'debit',
            // 'merchant' => $nin['paymentItem'],
            "merchant"=>'Bills',
            'status' => 1,
            'transaction_details' => $details
        ]);

        $activity['activity']="vfd bill payment of ".$nin['paymentItem']." for N".$nin['amount'];
        $activity['user_id']=$userId;

        ActivityLog::createActivity($activity);

    }

    /**
     * @OA\Post(
     ** path="/api/v1/user/vfd/create-new-pin",
     *   tags={"VFD Bill Payment"},
     *   summary="Create Pin",
     *   operationId="Create Pin",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"pin","confirm_pin"},
     *              @OA\Property( property="pin", type="string", description="it should be 4 digits"),
     *              @OA\Property( property="confirm_pin", type="string", description="same as above")
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
    public function createBillPaymentPin(Request $request)
    {
        $data = $request->all();

        $validator = Validator::make($data, [
            //'pin' => 'required|numeric|unique:bill_payment_pins',
            'pin' => 'required|numeric|digits:4',
            'confirm_pin' => 'required|numeric|same:pin'
        ]);

        $pin = $data['pin'];

        if ($validator->fails())
        {
            return $this->sendError('Error',$validator->errors(),422);
        }

        if(!Auth::user()->id)
            return $this->sendError('Unauthorized Access',[],401);
        $userId = Auth::user()->id;

        // if($userId >= 204 && $userId <= 209){
        //     $pin = intval($pin) + 1000;
        // }

        $checkIfUserExist=BillPaymentPin::where('user_id', $userId)->first();
        if($checkIfUserExist)
        {
            $response=BillPaymentPin::where('user_id', $userId)->update(['pin'=>$pin]);
        }
        else{
            $response=BillPaymentPin::create([
                'user_id'=>$userId,
                'pin'=>$pin
            ]);
        }
        //return $this->sendError('User already created a pin',[],409);


        return $this->successfulResponse($response, 'New Pin successfully created');
    }

    /**
     * @OA\Post(
     ** path="/api/v1/user/vfd/reset-billpayment-pin",
     *   tags={"VFD Bill Payment"},
     *   summary="Reset Billpayment Pin",
     *   operationId="Reset Billpayment Pin",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"pin","confirm_new_pin"},
     *              @OA\Property( property="pin", type="string", description="it should be 4 digits"),
     *              @OA\Property( property="confirm_new_pin", type="string", description="same as above")
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
    public function resetBillPaymentPin(Request $request)
    {
        $data = $request->all();

        $validator = Validator::make($data, [
            'pin' => 'required|numeric|digits:4',
            'confirm_new_pin' => 'required|numeric|same:pin'
        ]);

        if ($validator->fails())
        {
            return $this->sendError('Error',$validator->errors(),422);
        }

        if(!Auth::user()->id)
            return $this->sendError('Unauthorized Access',[],401);
        $userId = Auth::user()->id;

        $checkIfUserExist=BillPaymentPin::where('user_id', $userId)->first();
        if($checkIfUserExist)
        {
            $response=BillPaymentPin::where('user_id', $userId)->update(['pin'=>$data['pin']]);
        }
        else{
            $response=BillPaymentPin::create([
                'user_id'=>$userId,
                'pin'=>$data['pin']
            ]);
        }
        //return $this->sendError('User already created a pin',[],409);


        return $this->successfulResponse($response, 'New Pin successfully set');
    }

    /**
     * @OA\Get(
     ** path="/api/v1/user/vfd/get-billpayments-history",
     *   tags={"VFD Bill Payment"},
     *   summary="Get Bill Payments History",
     *   operationId="Get Bill Payments History",
     *
     *   @OA\Response(
     *      response=200,
     *       description="Success",
     *     ),
     *     security={
     *       {"bearer_token": {}}
     *     }
     *
     *)
     **/
    public function viewBillPaymentHistory()
    {
        if(!Auth::user()->id)
            return $this->sendError('Unauthorized Access',[],401);

        $history=BillPaymentHistory::where('user_id', Auth::user()->id)->get();

        return $this->successfulResponse($history, 'Bill payment history');
    }

    /**
     * @OA\Get(
     ** path="/api/v1/user/vfd/get-cashback-rate",
     *   tags={"VFD Bill Payment"},
     *   summary="Get VFD cash back rate",
     *   operationId="Get VFD cash back rate",
     *
     *   @OA\Response(
     *      response=200,
     *       description="Success",
     *     ),
     *     security={
     *       {"bearer_token": {}}
     *     }
     *
     *)
     **/
    public function vfdDiscount()
    {
        if(!Auth::user()->id)
            return $this->sendError('Unauthorized Access',[],401);

        $discount=VfdDiscount::get([
            'uuid',
            'category',
            'biller',
            'biller_id',
            'percent'
        ]);

        return $this->successfulResponse($discount, 'VFD cash back rate');
    }

    /**
     * @OA\Post(
     ** path="/api/v1/user/claim-referral-bonus",
     *   tags={"User"},
     *   summary="Claim Referral Bonus",
     *   operationId="Claim Referral Bonus",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"amount"},
     *              @OA\Property( property="amount", type="number", description="Referral bonus total amount")
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
    public function referralBonus(Request $request)
    {
        $data = $request->all();
        
        $validator = Validator::make($data, [
            'amount' => 'required|numeric'
        ]);

        if ($validator->fails())
        {
            return $this->sendError('Error',$validator->errors(),422);
        }

        if(!Auth::user()->id)
            return $this->sendError('Unauthorized Access',[],401);
        $userId = Auth::user()->id;

        $claimedBonus=ClaimedBonus::where('user_id', $userId)->sum('amount');

        $referrals = UserReferral::where('referral_id', $userId)->get();
        
        $totalBonus = 0;
        foreach ($referrals as $referral) 
        {
            $deposit = $referral->user->transactions()->where('type', 'credit')->sum('amount'); 
            
            if ($deposit >= 1000) {
                $totalBonus += 100;
            } elseif ($deposit >= 500) {
                $totalBonus += 50;
            }
        }
        $unClaim = $totalBonus - $claimedBonus; 
        $amount = $data['amount'];

        if($amount > $unClaim)
        {
            return $this->sendError('Transaction failed, Try again letter',[], 422);
            exit();
        }

        DB::transaction(function () use($userId, $amount){
            ClaimedBonus::create([
                'user_id' => $userId,
                'amount' => $amount
            ]);

            WalletService::addToWallet($userId, $amount, 'naira');
        });

        $response = [
            'success' => true,
            'message' => "Referral bonus successfully claimed"
        ];

        return response()->json($response, 200);
    }
}
