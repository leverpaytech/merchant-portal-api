<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\BaseController;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\{User,Kyc,ExchangeRate, TopupReques, CardType, DocumentType, Country};
use Illuminate\Http\Request;
use Webpatser\Uuid\Uuid;
use App\Models\PaymentOption;
use App\Http\Resources\PaymentOptionResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class AdminController extends BaseController
{
    /**
     * @OA\Post(
     ** path="/api/v1/admin/add-payment-option",
     *   tags={"Admin"},
     *   summary="Create new payment option",
     *   operationId="create payment option",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"name","icon"},
     *              @OA\Property( property="name", type="string"),
     *              @OA\Property( property="icon", type="string")
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
    public function createPaymentOption(Request $request){
        $this->validate($request, [
            'name'=>'required|string',
            'icon'=>'required|string'
        ]);
        $payment = new PaymentOption();
        $payment->uuid = Uuid::generate()->string;
        $payment->name = $request['name'];
        $payment->icon = $request['icon'];
        $payment->status = 0;
        $payment->save();

        return new PaymentOptionResource($payment);
    }

    /**
     * @OA\Get(
     ** path="/api/v1/admin/get-payment-options",
     *   tags={"Admin"},
     *   summary="Get all payment options",
     *   operationId="get all payment options",
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
    public function getPaymentOption()
    {
        return $this->successfulResponse(PaymentOption::where('status',1)->orWhere('status', 0)->get(), 'payment optons successfully retrieved');
    }

   /****************************merchants services****************************/
    /**
     * @OA\Get(
     ** path="/api/v1/admin/get-all-merchants",
     *   tags={"Admin"},
     *   summary="Get all merchants",
     *   operationId="get all merchants",
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
    public function getAllMerchants()
    {
        if(!Auth::user()->id)
        {
            return $this->sendError("Authourized user",[], 401);
        }
        $users=User::where('role_id', '1')->get();

        return $this->successfulResponse($users, 'Merchants list');
       //  return $this->successfulResponse(new UserResource($users), 'success');
    }

        /****************************user services****************************/
    /**
     * @OA\Get(
     ** path="/api/v1/admin/get-topup-requests",
     *   tags={"Admin"},
     *   summary="Get all topup request",
     *   operationId="get all topup request",
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
    public function getAllTopupRequests(Request $request)
    {
        if(!Auth::user()->id)
        {
            return $this->sendError("Authourized user",[], 401);
        }

        $filter = strval($request->query('status'));

        if($filter == 'pending'){
            $topup = TopupRequest::where('status', 0)->orderBy('created_at', 'desc')->get();
        }else if($filter == 'paid'){
            $topup = TopupRequest::where('status', 1)->orderBy('created_at', 'desc')->get();
        }else{
            $topup = TopupRequest::orderBy('created_at', 'desc')->get();
        }

        return $this->successfulResponse($topup, 'Topup requests');
    }


    public function approveTopupRequest(Request $request){
        $this->validate($request, [
            'uuid'=>'required|string'
        ]);
    }

    /****************************user services****************************/
    /**
     * @OA\Get(
     ** path="/api/v1/admin/get-all-users",
     *   tags={"Admin"},
     *   summary="Get all user",
     *   operationId="get all user",
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
    public function getAllUsers()
    {
        if(!Auth::user()->id)
        {
            return $this->sendError("Authourized user",[], 401);
        }

        $users=User::where('role_id','0')->with('kyc')->get();
        $users->transform(function($user){
            if($user->kyc !==NULL)
            {
                $county=Country::find($user->kyc->country_id);
                $docType=DocumentType::find($user->kyc->document_type_id);
                $user->kyc->country=[
                    'country_id'=>$county->id,
                    'country_name'=>$county->country_name,
                ];
                $user->kyc->document_type=[
                    'document_type_id'=>$docType->id,
                    'name'=>$docType->name,
                ];
            
                return $user;
            }
            
        });
        return $this->successfulResponse($users, 'Users List');
    }

    /**
     * @OA\Get(
     ** path="/api/v1/admin/get-all-users-kyc-list",
     *   tags={"Admin"},
     *   summary="Get all users kyc list",
     *   operationId="Get all users kyc list",
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
    public function getUserKyc()
    {
        $kycs=Kyc::where('status', '0')->orderBy('status', 'DESC')->with('user')->with('country')->with('documentType')->get();

        return $this->successfulResponse($kycs, 'kyc details successfully retrieved');

    }

    /**
     * @OA\Get(
     ** path="/api/v1/admin/get-all-merchants-kyc-list",
     *   tags={"Admin"},
     *   summary="Get all merchants kyc list",
     *   operationId="Get all merchants kyc list",
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
    public function getMerchantKyc()
    {
        $kycs=Kyc::where('status', '1')->orderBy('status', 'DESC')->with('user')->with('country')->with('documentType')->get();

        return $this->successfulResponse($kycs, 'Merchants kyc details successfully retrieved');

    }

    /**
     * @OA\Get(
     ** path="/api/v1/admin/find-kyc/{uuid}",
     *   tags={"Admin"},
     *   summary="Find Kyc by uuid",
     *   operationId="Find Kyc by uuid",
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
    public function findKyc($uuid)
    {
        $getY=User::query()->where('uuid',$uuid)->get()->first();
        if(!$getY)
        {
            return $this->sendError('Invalid UUID','',400);
        }
        $kycs=Kyc::where('user_id', $getY->id)->with('user')->with('country')->with('documentType')->get();

        return $this->successfulResponse($kycs, 'KYC details successfully find');

    }

    /**
     * @OA\Post(
     ** path="/api/v1/admin/add-exchange-rate",
     *   tags={"Admin"},
     *   summary="Add new exchange rate",
     *   operationId="Add new exchange rate",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"rate"},
     *              @OA\Property( property="rate", type="string")
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
    public function addExchangeRate(Request $request)
    {
        $data = $request->all();

        $validator = Validator::make($data, [
            'rate'=>'unique:exchange_rates,rate|required|numeric'
        ]);

        if ($validator->fails())
        {
            return $this->sendError('Error',$validator->errors(),422);
        }
        $new_rate=$request['rate'];
        DB::transaction( function() use($new_rate) {
            //de-active the active rate
            ExchangeRate::where('status',1)->update(['status'=>0]);

            //set new rate
            $exchangeRate = new ExchangeRate();
            $exchangeRate->rate = $new_rate;
            $exchangeRate->save();
        });

        return $this->successfulResponse($new_rate, 'New exchange rate successfully added');
    }

    /**
     * @OA\Get(
     ** path="/api/v1/admin/active-exchange-rate",
     *   tags={"Admin"},
     *   summary="Get active exchange rate",
     *   operationId="Get active exchange rate",
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
    public function activeExchangeRate()
    {
        $activeRate=ExchangeRate::where('status',1)->get()->first();

        return $this->successfulResponse($activeRate, 'active exchange rate successfully retrieved');

    }

    /**
     * @OA\Get(
     ** path="/api/v1/admin/all-exchange-rate",
     *   tags={"Admin"},
     *   summary="Get all exchange rate",
     *   operationId="Get all exchange rate",
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

    public function allExchangeRate()
    {
        $activeRate=ExchangeRate::orderBy('status', 'DESC')->get();

        return $this->successfulResponse($activeRate, 'all exchange rate successfully retrieved');

    }

    

}
