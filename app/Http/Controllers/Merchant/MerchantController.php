<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\BaseController;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\Currency;
use App\Models\User;
use App\Models\{MerchantKeys,Merchant,Kyc};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class MerchantController extends BaseController
{
    protected $userModel;

    public function __construct(User $user)
    {
        $this->userModel = $user;
    }

    /**
     * @OA\Get(
     ** path="/api/v1/merchant/get-merchant-profile",
     *   tags={"Merchant"},
     *   summary="Get merchant profile",
     *   operationId="get merchant profile details",
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
    public function getMerchantProfile()
    {
        return $this->successfulResponse(new UserResource(Auth::user()));
    }

    /**
     * @OA\Post(
     ** path="/api/v1/merchant/update-merchant-profile",
     *   tags={"Merchant"},
     *   summary="Update merchant profile",
     *   operationId="Update merchant profile",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"email", "first_name","last_name","address", "business_name", "phone", "country_id", "state_id", "city_id"},
     *              @OA\Property( property="first_name", type="string"),
     *              @OA\Property( property="last_name", type="string"),
     *              @OA\Property( property="email", type="string"),
     *              @OA\Property( property="address", type="string"),
     *              @OA\Property( property="business_name", type="string"),
     *              @OA\Property( property="phone", type="string"),
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
    public function updateMerchantProfile(Request $request)
    {
        $userId = Auth::user()->id;

        $data = $this->validate($request, [
            'first_name' => 'required',
            'last_name' => 'required',
            'address' => 'required',
            'country_id' => 'required',
            'state_id' => 'required',
            'city_id' => 'required',
            'dob' => 'nullable',
            'gender' => 'nullable',
            'phone' => 'required|unique:users,phone,'.$userId,
            'passport' => 'nullable',
            'business_address'=> 'required',
            'business_phone'=> 'required',

        ]);


        if(!$user = User::find($userId))
            return $this->sendError('User not found',[],404);
        if(isset($request->passport))
        {
            try
            {

                //$newname= $userId.''.time().'.'.$request->passport->extension();
                //$request->passport->move(public_path('passports'), $newname);
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

        $user = $this->userModel->updateUser($data,$userId);
        $data2['activity']="Merchant Profile Update";
        $data2['user_id']=$userId;


        $merchant = Merchant::where('user_id', $userId)->first();
        $merchant->business_address=$data['business_address'];
        $merchant->business_phone=$data['business_phone'];
        $merchant->save();

        ActivityLog::createActivity($data2);

        return $this->successfulResponse(new UserResource($user), 'Merchant profile successfully updated');
    }

    /**
     * @OA\Get(
     ** path="/api/v1/merchant/get-merchant-currencie",
     *   tags={"Merchant"},
     *   summary="Get merchant's currencies",
     *   operationId="get merchant's currencies",
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
     ** path="/api/v1/merchant/add-currencies",
     *   tags={"Merchant"},
     *   summary="Add merchant curriencies",
     *   operationId="Add merchant curriencies",
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

    /**
     * @OA\Get(
     ** path="/api/v1/merchant/get-merchant-keys",
     *   tags={"Merchant"},
     *   summary="Get merchant's merchant keys",
     *   operationId="get merchant's merchant keys",
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
    public function getMerchantKeys()
    {
        $userId=Auth::user()->id;
        $testKeys=MerchantKeys::where('user_id', $userId)
            ->get()
            ->first();
        return $this->successfulResponse($testKeys, '');
    }

    /**
     * @OA\Get(
     ** path="/api/v1/merchant/get-merchant-account",
     *   tags={"Merchant"},
     *   summary="Get merchant virtual account",
     *   operationId="get merchant virtual account",
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
    public function getMerchantAccount(){
        $account = Account::where('user_id', Auth::id())->where('type', 'reserved')->first();
        return $this->successfulResponse($account, '');
    }

    /**
     * @OA\Post(
     ** path="/api/v1/merchant/change-mode",
     *   tags={"Merchant"},
     *   summary="Change to mode from test mode to live mode or vise versa",
     *   operationId="Change test mode to live ",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"mode"},
     *              @OA\Property( property="mode", type="string"),
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
    public function changeMode(Request $request)
    {
        $validator = Validator::make($data, [
            'mode' => 'required'
        ]);

        $userId=Auth::user()->id;
        $merchantKeys=getMerchantKeys::where('user_id',$userId)->get()->first();

        if(!$merchantKeys)
        {
            return $this->sendError('Merchant key does not exists','',400);
        }

        $merchantKeys->stage = $request->mode;
        $merchantKeys->save();

        return $this->successfulResponse($merchantKeys, $request->mode.'mode successfully activated');
    }

    /**
     * @OA\Post(
     ** path="/api/v1/merchant/add-merchant-kyc",
     *   tags={"Merchant"},
     *   summary="Add Merchant KYC document",
     *   operationId="Add Merchant KYC document",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"document_type_id","country_id","business_address","id_card_front","bvn","nin","business_certificate","rc_number"},
     *              @OA\Property( property="document_type_id", enum="[1]"),
     *              @OA\Property( property="id_card_front", type="file"),
     *              @OA\Property( property="id_card_back", type="file"),
     *              @OA\Property( property="country_id", enum="[1]"),
     *              @OA\Property( property="bvn", type="string"),
     *              @OA\Property( property="nin", type="string"),
     *              @OA\Property( property="business_address", type="string"),
     *              @OA\Property( property="business_certificate", type="file"),
     *              @OA\Property( property="rc_number", type="string")
     *
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
    public function addMerchantKyc(Request $request)
    {
        $data = $request->all();

        $validator = Validator::make($data, [
            'document_type_id' => 'required',
            'id_card_front' => 'required|mimes:jpeg,png,jpg|max:2048',
            'id_card_back' => 'nullable|mimes:jpeg,png,jpg|max:2048',
            'country_id' => 'required',
            'state_id' => 'nullable',
            'bvn' => 'required|numeric',
            'nin' => 'required|numeric',
            'business_address' => 'required',
            'business_certificate'=>'required|nullable|mimes:jpeg,png,jpg|max:2048',
            'rc_number'=>'required'
        ],[
            'document_type_id.required'=>'Document type is required',
            'country_id.required'=>'Country is required',
            'rc_number.required'=>'RC number or CAC number is required',
        ]);

        if ($validator->fails())
        {
            return $this->sendError('Error',$validator->errors(),422);
        }

        $user_id=Auth::user()->id;

        $data['user_id']=$user_id;

        $idFront = cloudinary()->upload($request->file('id_card_front')->getRealPath(),
            ['folder'=>'leverpay/kyc']
        )->getSecurePath();
        $data['id_card_front']=$idFront;


        if($request->has('id_card_back'))
        {
            $idBack = cloudinary()->upload($request->file('id_card_back')->getRealPath(),
            ['folder'=>'leverpay/kyc']
            )->getSecurePath();
            $data['id_card_back']=$idBack;
        }

        if($request->has('business_certificate'))
        {
            $bsCert = cloudinary()->upload($request->file('business_certificate')->getRealPath(),
            ['folder'=>'leverpay/kyc']
            )->getSecurePath();
            $data['business_certificate']=$bsCert;
        }
        $data['card_type'] = 100;

        $user=Kyc::create($data);
        // User::where('id', $user_id)->update(['kyc_status'=>1]);

        $data2['activity']="Add Merchant KYC";
        $data2['user_id']=$user_id;

        ActivityLog::createActivity($data2);

        $response = [
            'success' => true,
            'merchant' =>$user,
            'message' => "Merchant KYC submitted"
        ];
        return $this->successfulResponse($user, "Merchant KYC submitted");
    }

    /**
     * @OA\Get(
     ** path="/api/v1/merchant/merchant-kyc-details",
     *   tags={"Merchant"},
     *   summary="Get merchant kyc details",
     *   operationId="Get merchant kyc details",
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
    public function getKycDocument()
    {
        $user_id=Auth::user()->id;
        $kycs=Kyc::join('countries','countries.id','=','kycs.country_id')
            ->join('document_types','document_types.id','=','kycs.document_type_id')
            ->where('user_id', $user_id)
            ->get([
                'document_types.name',
                'kycs.id_card_front',
                'kycs.id_card_back',
                'countries.country_name',
                'kycs.bvn',
                'kycs.nin',
                'kycs.business_address',
                'kycs.business_certificate',
                'kycs.rc_number',
                'kycs.status',
            ]);

        return $this->successfulResponse($kycs, 'merchant kyc details successfully retrieved');

    }
}
