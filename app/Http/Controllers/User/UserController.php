<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\BaseController;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Resources\CardResource;
use App\Models\ActivityLog;
use App\Models\Currency;
use App\Models\{User,Transaction};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use App\Mail\ChangePhoneAndEmailVerifier;
use Illuminate\Validation\Rule;
use App\Services\CardService;


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
        $user = User::where('id', $userId)->with('currencies')->with('state')->with('city')->get()->first();
        $wallet=Auth::user()->wallet;
        $user->wallet_balance=$wallet->amount;
        $getV1=Transaction::where('user_id',$userId)->where('type','credit')->sum('amount');
        $user->total_save=$getV1;
        $getV2=Transaction::where('user_id',$userId)->where('type','debit')->sum('amount');
        $user->total_spending=$getV2;


        if(!$user)
            return $this->sendError('User not found',[],404);
        return $this->successfulResponse(new UserResource($user));
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
     *              @OA\Property( property="other_name", type="string"),
     *              @OA\Property( property="other_email", type="string"),
     *              @OA\Property( property="other_phone", type="string"),
     *              @OA\Property( property="primary_email", type="string"),
     *              @OA\Property( property="primary_phone", type="string"),
     *              @OA\Property( property="gender", type="string"),
     *              @OA\Property( property="country_id", enum="[1]"),
     *              @OA\Property( property="state_id", enum="[1]"),
     *              @OA\Property( property="city_id", enum="[1]"),
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
            'passport' => 'nullable|mimes:jpeg,png,jpg,gif|max:2048'
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
            Mail::to($email)->send(new ChangePhoneAndEmailVerifier($name, $verifyToken));
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
     ** path="/api/v1/user/get-user-currencies}",
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


    public function generateAccNo()
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
    }

    private function getToken($authurl,$wallet_credentials)
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

    }

    public function onBoarding()
    {
        $requestData=array(
            "username"=>"leverpay",
            "walletName"=>"leverpayWallet",
            "webhookUrl"=>"",
            "shortName"=>"LPCT",
            "implementation"=>"Pool"
        );
        $postData= json_encode($requestData);

        $ch1 = curl_init();
        
        curl_setopt_array($ch1, array(
            CURLOPT_URL => "https://api-devapps.vfdbank.systems/vtech-wallet/api/v1.1/wallet2/onboarding?wallet-credentials=Q0xydGRyZFYyMW5WZXBwaXRzSjNXZzV2d2dZYTpzNVRocm54WlhpU3JaT1VSYjhfUzdzUlNsSlFh",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
                "Authorization: Bearer {{VFDBankAuth}}"
                )
            ));               
        $acc_responses = curl_exec($ch1);
        curl_close($ch1);
        
        $acc_responses = json_decode($acc_responses);

        return $acc_responses;

    }


}
