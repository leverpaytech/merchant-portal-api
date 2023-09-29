<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\BaseController;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\{User,Kyc,ExchangeRate, TopupReques, CardType, DocumentType, Country, Transaction};
use App\Models\Bank;
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
        $activeRate=ExchangeRate::latest()->first();

        return $this->successfulResponse($activeRate, 'active exchange rate successfully retrieved');

    }

    /**
     * @OA\Get(
     ** path="/api/v1/admin/get-exchange-rates-history",
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

    public function getExchangeRatesHistory()
    {
        $rates=ExchangeRate::latest()->get();

        return $this->successfulResponse($rates, 'all exchange rate successfully retrieved');

    }

     /**
     * @OA\Get(
     ** path="/api/v1/admin/get-transactions",
     *   tags={"Admin"},
     *   summary="Get all Transactions",
     *   operationId="Get all Transactions",
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

    public function getTransactions(){
        $transactions = Transaction::latest()->with('user')->get();
        return $this->successfulResponse($transactions, '');
    }

    /**
     * @OA\Post(
     ** path="/api/v1/admin/update-exchange-rates",
     *   tags={"Admin"},
     *   summary="update/change exchange rate",
     *   operationId="update/change exchange rate",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"rate","local_transaction_rate","international_transaction_rate","conversion_rate","funding_rate"},
     *              @OA\Property( property="rate", type="string"),
     *              @OA\Property( property="local_transaction_rate", type="string"),
     *              @OA\Property( property="funding_rate", type="string"),
     *              @OA\Property( property="conversion_rate", type="string"),
     *              @OA\Property( property="international_transaction_rate", type="string")
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
    public function updateExchangeRates(Request $request){
        $data = $this->validate($request, [
            'rate'=>'required|numeric',
            'local_transaction_rate'=>'required|numeric',
            'international_transaction_rate'=>'required|numeric',
            'funding_rate'=>'required|numeric',
            'conversion_rate'=>'required|numeric',
            'notes'=>'nullable|string'
        ]);

        ExchangeRate::create($data);
        return $this->successfulResponse([], 'Rate updated successfully');
    }


    /**
     * @OA\Post(
     ** path="/api/v1/admin/add-new-bank",
     *   tags={"Admin"},
     *   summary="Update exchange rates",
     *   operationId="Update exchange rates",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"name"},
     *              @OA\Property( property="name", type="string"),
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
    public function addNewBank(Request $request){
        $data = $this->validate($request, [
            'name'=>'required|string|unique:banks,name',
        ]);

        Bank::create($data);
        return $this->successfulResponse([], 'Bank created successfully');
    }

    /**
     * @OA\Post(
     ** path="/api/v1/admin/add-account-number",
     *   tags={"Admin"},
     *   summary="Add Account Number",
     *   operationId="Add Account Number",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"bank","account_number",'account_name'},
     *              @OA\Property( property="bank", type="string"),
     *              @OA\Property( property="account_number", type="string"),
     *              @OA\Property( property="account_name", type="string"),
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
    public function addAccountNo(Request $request){
        $data = $this->validate($request, [
            'bank'=>'required|string',
            'account_number'=>'required',
            'account_name'=>'required'
        ]);

        DB::table('lever_pay_account_no')->insert($data);

        return $this->successfulResponse([], 'Bank created successfully');
    }

       /**
     * @OA\Get(
     ** path="/api/v1/admin/get-account-numbers",
     *   tags={"Admin"},
     *   summary="Get all LeverPay Account number",
     *   operationId="Get all LeverPay Account number",
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

     public function getAccountNos(){
        $acc = DB::table('lever_pay_account_no')->get();
        return $this->successfulResponse($acc, 'Bank created successfully');
     }

}
