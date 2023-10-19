<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\BaseController;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\{User,Kyc,ExchangeRate, ActivityLog, TopupReques, CardType, DocumentType, Country, Transaction, ContactUs, Invoice};
use App\Models\Bank;
use App\Models\Card;
use App\Models\TopupRequest;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Webpatser\Uuid\Uuid;
use Illuminate\Support\Facades\Mail;
use App\Models\PaymentOption;
use App\Http\Resources\PaymentOptionResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Mail\GeneralMail;
use App\Services\SmsService;

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
        $users=User::where('role_id', '1')->with('kyc')->with('wallet')->get();
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
            }
            return $user;
        });
        return $this->successfulResponse($users, 'Merchants list');

    }

    public function getUser($uuid)
    {
        $user = User::where('uuid', $uuid)->with('merchant')->first();

        if(!$user){
            return $this->sendError("Merchant not found",[],400);
        }

        return $this->successfulResponse($user, '');
       
    }

        /****************************user services****************************/
    /**
     * @OA\Get(
     ** path="/api/v1/admin/get-topup-requests",
     *   tags={"Admin"},
     *   summary="Get all topup requests",
     *   operationId="get all topup requests",
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
            $topup = TopupRequest::where('status', 0)->orderBy('created_at', 'desc')->with('user')->get();
        }else if($filter == 'paid'){
            $topup = TopupRequest::where('status', 1)->orderBy('created_at', 'desc')->with('user')->get();
        }else{
            $topup = TopupRequest::orderBy('created_at', 'desc')->with('user')->get();
        }

        return $this->successfulResponse($topup, 'Topup requests');
    }

    public function getTopupRequest($uuid){
        $topup = TopupRequest::where('uuid', $uuid)->with('user')->first();
        if(!$topup){
            return $this->sendError("Topup request not found",[], 400);
        }
        return $this->successfulResponse($topup,"");
    }


      /**
     * @OA\Post(
     ** path="/api/v1/admin/approve-topup-request",
     *   tags={"Admin"},
     *   summary="Approve Topup Request",
     *   operationId="Approve Topup Request",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"uuid"},
     *              @OA\Property( property="uuid", type="string"),
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


    public function approveTopupRequest(Request $request){
        $this->validate($request, [
            'uuid'=>'required|string'
        ]);

        $topup = TopupRequest::where('uuid', $request['uuid'])->first();
        if(!$topup){
            abort(400, 'Topup request not found');
        }

        if($topup->status != 0){
            abort(400, 'Topup request is already processed');
        }

        $user = User::find($topup->user_id);
        if(!$user){
            abort(400, 'User not found');
        }

        $ext = 'LP_'.Uuid::generate()->string;
        $transaction = new Transaction();
        $transaction->user_id = $topup->user_id;
        $transaction->reference_no	= 'LP_'.Uuid::generate()->string;
        $transaction->tnx_reference_no	= $ext;
        $transaction->amount =$topup->amount;
        $transaction->balance = floatval($user->wallet->withdrawable_amount) + floatval($topup->amount);
        $transaction->type = 'credit';
        $transaction->merchant = 'topup';
        $transaction->status = 1;

        $details = [
            "topup_uuid"=>$topup->uuid
        ];
        $transaction->transaction_details = json_encode($details);
        $transaction->save();

        WalletService::addToWallet($user->id, $topup->amount);

        $topup->status = 1;
        $topup->save();

        return $this->successfulResponse([], 'Request approved');
    }

    public function cancelTopupRequest(Request $request){
        $this->validate($request, [
            'uuid'=>'required|string'
        ]);

        $topup = TopupRequest::where('uuid', $request['uuid'])->first();
        if(!$topup){
            abort(400, 'Topup request not found');
        }

        if($topup->status != 2){
            abort(400, 'Topup request is already processed');
        }

        $topup->status = 2;
        $topup->save();

        return $this->successfulResponse([], 'Request cancelled');
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
            }
            return $user;
        });
        return $this->successfulResponse($users, 'Users List');
    }

    /**
     * @OA\Get(
     ** path="/api/v1/admin/get-users-kyc-list",
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
        $kycs=Kyc::join('users','users.id','=','kycs.user_id')
            ->where('users.role_id', '0')
            ->orderBy('kycs.status', 'DESC')
            ->with('user')
            ->with('country')
            ->with('documentType')
            ->get();

        return $this->successfulResponse($kycs, 'kyc details successfully retrieved');

    }

    /**
     * @OA\Get(
     ** path="/api/v1/admin/get-merchants-kyc-list",
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
        $kycs=Kyc::join('users','users.id','=','kycs.user_id')
            ->where('users.role_id', '1')
            ->orderBy('kycs.status', 'DESC')
            ->with('user')
            ->with('country')
            ->with('documentType')
            ->get();

        return $this->successfulResponse($kycs, 'Merchants kyc details successfully retrieved');

    }

    public function approveKyc($id){
        $kyc = Kyc::find($id);
        if(!$kyc){
            return $this->sendError('Kyc not found',[],400);
        }

        $kyc->status = 1;
        $kyc->save();

        User::where('id', $kyc->user_id)->update(['kyc_status'=>1]);

        Card::where('user_id', $kyc->user_id)->update(['type'=>$kyc->card_type]);
        return $this->successfulResponse($kyc, 'Kyc approved successfully');
    }

    /**
     * @OA\Get(
     ** path="/api/v1/admin/find-kyc/{uuid}",
     *   tags={"Admin"},
     *   summary="Find Kyc by uuid",
     *   operationId="Find Kyc by uuid",
     *
     * * * @OA\Parameter(
     *      name="uuid",
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
            'rate'=>'nullable|numeric',
            'local_transaction_rate'=>'nullable|numeric',
            'international_transaction_rate'=>'nullable|numeric',
            'funding_rate'=>'nullable|numeric',
            'conversion_rate'=>'nullable|numeric',
            'notes'=>'nullable|string'
        ]);
        $latest=ExchangeRate::latest()->get()->first();

        $data2=[
            'rate'=>$latest->rate,
            'local_transaction_rate'=>$latest->local_transaction_rate,
            'international_transaction_rate'=>$latest->international_transaction_rate,
            'funding_rate'=>$latest->funding_rate,
            'conversion_rate'=>$latest->conversion_rate,
            'notes'=>$latest->notes
        ];
        $val=false;
        if(!empty($request->rate)) { $data2['rate']=$request->rate; $val=true;}
        if(!empty($request->local_transaction_rate)) { $data2['local_transaction_rate']=$request->local_transaction_rate; $val=true; }
        if(!empty($request->international_transaction_rate)) { $data2['international_transaction_rate']=$request->international_transaction_rate; $val=true; }
        if(!empty($request->funding_rate)) { $data2['funding_rate']=$request->funding_rate; $val=true; }
        if(!empty($request->conversion_rate)) { $data2['conversion_rate']=$request->conversion_rate; $val=true; }
        if(!empty($request->notes)) { $data2['notes']=$request->notes; }

        if($val==false)
        {
            return $this->sendError("Atleast one rate value is required",[],400);
            exit();
        }

        ExchangeRate::create($data2);
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
     *              required={"bank","account_number","account_name"},
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

    /**
     * @OA\Get(
     ** path="/api/v1/admin/get-contact-us-messages",
     *   tags={"Admin"},
     *   summary="Get all contact us messages",
     *   operationId="Get all contact us messages",
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

    public function getContactUsForms()
    {
        $list = DB::table('contact_us')->orderBy('status', 'ASC')->get();
        return $this->successfulResponse($list, 'Contact Us messages');
    }

    /**
     * @OA\Post(
     ** path="/api/v1/admin/reply-message",
     *   tags={"Admin"},
     *   summary="Reply user contact message",
     *   operationId="Reply user contact message",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"uuid","reply"},
     *              @OA\Property( property="uuid", type="string"),
     *              @OA\Property( property="reply", type="string")
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

    public function replyMessage(Request $request)
    {
        $data = $this->validate($request, [
            'uuid'=>'required|string',
            'reply'=>'required|string'
        ]);

        $getEmail=ContactUs::where('uuid',$data['uuid'])->get()->first();
        if(empty($getEmail['email']))
        {
            return $this->sendError("Invalid UUID",[], 401); exit();
        }

        $getEmail->reply=$data['reply'];
        $getEmail->status=1;
        $getEmail->save();

        //sent mail
        SmsService::sendMail("",$data['reply'], "LeverPay Replied Message", $getEmail->email);
        //SmsService::sendMail("Dear {$getEmail->email},", $data['reply'], "LeverPay Replay Message", $getEmail->email);

        return $this->successfulResponse([], 'Reply message successfully sent');
    }

    /**
     * @OA\Get(
     ** path="/api/v1/admin/get-all-invoices",
     *   tags={"Admin"},
     *   summary="Get list of invoices",
     *   operationId="get list of invoices",
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

     public function getInvoices(Request $request){
        $invoices = Invoice::with('user')->with('merchant')->get();

        return $this->successfulResponse($invoices, '');
    }

    /**
     * @OA\Get(
     ** path="/api/v1/admin/get-user-details/{uuid}",
     *   tags={"Admin"},
     *   summary="Get user details by uuid",
     *   operationId="Get user details by uuid",
     *
     * * * @OA\Parameter(
     *      name="uuid",
     *      in="path",
     *      required=true,
     *      @OA\Schema(
     *           type="string",
     *      )
     *   ),
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
    public function getUserDetails($uuid)
    {
        if(empty($uuid))
            return $this->sendError('UUID cannot be empty',[],401);
        //active exchange rate
        $getExchageRate=ExchangeRate::where('status',1)->latest()->first();
        $rate=$getExchageRate->rate;

        $user = User::where('uuid', $uuid)->where('role_id', '0')->with('wallet')->with('card')->with('currencies')->with('state')->with('city')->get()->first();

        if(!$user)
            return $this->sendError('User not found',[],404);

        $getV1=Transaction::where('user_id',$user->id)->where('type','credit')->sum('amount');
        $user->total_save= [
            'ngn'=>$getV1,
            'usdt'=>round($getV1/$rate,6)
        ];
        $getV2=Transaction::where('user_id',$user->id)->where('type','debit')->sum('amount');
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

        //transactions history
        $user->transaction_history=Transaction::where('user_id',$user->id)->get();

        //kyc details
        $user->kyc_details=Kyc::where('user_id', $user->id)->with('country')->with('documentType')->get();

        return $this->successfulResponse($user, 'User details successfully retrieved');
    }

    /**
     * @OA\Get(
     ** path="/api/v1/admin/get-merchant-details/{uuid}",
     *   tags={"Admin"},
     *   summary="Get merchant details by uuid",
     *   operationId="Get merchant details by uuid",
     *
     * * * @OA\Parameter(
     *      name="uuid",
     *      in="path",
     *      required=true,
     *      @OA\Schema(
     *           type="string",
     *      )
     *   ),
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
    public function getMerchantDetails($uuid)
    {
        $user = User::where('uuid', $uuid)->where('role_id', '1')->with('merchant')->with('wallet')->first();
        
        if(!$user){
            return $this->sendError("Merchant not found",[],400);
        }
        //transactions history
        $user->transaction_history=Transaction::where('user_id',$user->id)->get();
        //kyc details
        $user->kyc_details=Kyc::where('user_id', $user->id)->with('country')->with('documentType')->get();

        return $this->successfulResponse($user, '');
       
    }

    /**
     * @OA\Post(
     ** path="/api/v1/admin/activate-account",
     *   tags={"Admin"},
     *   summary="Activate account",
     *   operationId="Activate account",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"uuid"},
     *              @OA\Property( property="uuid", type="string")
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
    public function activate(Request $request)
    {
        $data = $request->all();
        $validator = Validator::make($data, [
            'uuid' => 'string|required'
        ]);

        if ($validator->fails())
            return $this->sendError('Error',$validator->errors(),422);
        
        $user=User::where('uuid', $data['uuid'])->get()->first();
        if(!$user)
            return $this->sendError("Account not found",[],400);

        $user->status = true;
        $user->save();

        $data2['activity']="Account successfully activated";
        $data2['user_id']=Auth::user()->id;
        ActivityLog::createActivity($data2);

        $response = [
            'success' => true,
            'message' => "Account successfully activated"
        ];

        return response()->json($response, 200);
    }

    /**
     * @OA\Post(
     ** path="/api/v1/admin/deactivate-account",
     *   tags={"Admin"},
     *   summary="Deactivate account",
     *   operationId="Deactivate account",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"uuid"},
     *              @OA\Property( property="uuid", type="string")
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
    public function deActivate(Request $request)
    {
        $data = $request->all();
        $validator = Validator::make($data, [
            'uuid' => 'string|required'
        ]);

        if ($validator->fails())
            return $this->sendError('Error',$validator->errors(),422);
        
        $user=User::where('uuid', $data['uuid'])->get()->first();
        if(!$user)
            return $this->sendError("Account not found",[],400);
            
        $user->status = false;
        $user->save();

        $data2['activity']="Account successfully activated";
        $data2['user_id']=Auth::user()->id;
        ActivityLog::createActivity($data2);

        $response = [
            'success' => true,
            'message' => "Account successfully Deactivated"
        ];

        return response()->json($response, 200);
    }

}
