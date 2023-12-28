<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\BaseController;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\{User,Kyc,ExchangeRate, ActivityLog, TopupReques, CardType, DocumentType, Country, Transaction, ContactUs, Invoice, MerchantKeys, SubmitPayment,Remittance,Voucher};
use App\Models\Account;
use App\Models\Bank;
use App\Models\Card;
use App\Models\Investment;
use App\Models\Merchant;
use App\Models\TopupRequest;
use App\Models\Transfer;
use App\Models\UserBank;
use App\Models\Wallet;
use App\Services\CardService;
use App\Services\ProvidusService;
use App\Services\WalletService;
use App\Services\ZeptomailService;
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
    public function getAllMerchants(Request $request)
    {
        if(!Auth::user()->id)
        {
            return $this->sendError("Authourized user",[], 401);
        }
        // $users=User::where('role_id', '1')->with('kyc')->with('wallet')->get();
        $users=User::where('role_id', '1');
        $mode = strval($request->query('mode'));
        //mode == 0 means those that haven't submit kyc
        //mode == 1 means those that submit kyc and havs been approved
        //mode == 2 means those that haven submit kyc but hasn't been approved
        if($mode == 1){
            $users = $users->where('kyc_status', 1)->with('kyc')->with('wallet')->get();
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
        }elseif($mode == 0){
            $users = $users->where('kyc_status', 0)->doesntHave('kyc')->with('wallet')->get();
        }elseif($mode == 2){
            $users = $users->whereHas('kyc', function ($query) {
                $query->where('status', 0);
            })->with('kyc')->get();
        }else {
            $users = $users->with('kyc')->with('wallet')->get();
        }
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
        }else if($filter == 'approved'){
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
            //abort(400, 'Topup request not found');
            return $this->sendError("Topup request not found",[], 400);
        }

        if($topup->status != 0){
            //abort(400, 'Topup request is already processed');
            return $this->sendError("Topup request is already processed",[], 400);
        }

        $user = User::find($topup->user_id);
        if(!$user){
            //abort(400, 'User not found');
            return $this->sendError("User not found",[], 400);
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

        $content="Dear {$user->first_name}, The topup request for {$topup->amount} has been approved";
        ZeptomailService::sendMailZeptoMail("Topup Request Approval" ,$content, $user->email);

        return $this->successfulResponse([], 'Request approved');
    }

    /**
     * @OA\Post(
     ** path="/api/v1/admin/cancel-topup-request",
     *   tags={"Admin"},
     *   summary="cancel topup request",
     *   operationId="cancel topup request",
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
    public function cancelTopupRequest(Request $request){
        $this->validate($request, [
            'uuid'=>'required|string'
        ]);

        $topup = TopupRequest::where('uuid', $request['uuid'])->first();
        if(!$topup){
            //abort(400, 'Topup request not found');
            return $this->sendError("Topup request not found",[], 400);
        }

        if($topup->status != 0){
            //abort(400, 'Topup request is already processed');
            return $this->sendError("Topup request is already processed",[], 400);
        }

        $user = User::find($topup->user_id);
        if(!$user){
            //abort(400, 'User not found');
            return $this->sendError("User not found",[], 400);
        }
        $topup->status = 2;
        $topup->save();

        $content="Dear {$user->first_name}, the topup request for {$topup->amount} has been declined, contact our support with further evidence for help";
        ZeptomailService::sendMailZeptoMail("Topup Request Declined" ,$content, $user->email);

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
            ->where('users.kyc_status', '0')
            ->orderBy('kycs.status', 'DESC')
            ->with('user')
            ->with('country')
            ->with('documentType')
            ->select('kycs.*')
            ->paginate(20);

        return $this->successfulResponse($kycs, 'Merchants kyc details successfully retrieved');

    }

    /**
     * @OA\Get(
     ** path="/api/v1/admin/approve-kyc/{uuid}",
     *   tags={"Admin"},
     *   summary="Approve KYC",
     *   operationId="Approve KYC",
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
    public function approveKyc($uuid){
        $kyc = Kyc::where('uuid',$uuid)->first();
        if(!$kyc){
            return $this->sendError('Kyc not found',[],400);
        }

        if(!$kyc->bvn){
            return $this->sendError('KYC does not contain BVN',[],400);
        }
        if(!$kyc->nin){
            return $this->sendError('KYC does not contain NIN',[],400);
        }

        $kyc->status = 1;
        $kyc->save();

        User::where('id', $kyc->user_id)->update(['kyc_status'=>1]);

        if($kyc->user->role_id == 0){
            Card::where('user_id', $kyc->user_id)->update(['type'=>$kyc->card_type]);
            CardService::upgradeCardNumber($kyc->user_id, $kyc->card_type);
        }
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
        $list = DB::table('contact_us')->where('status','<', 2)->orderBy('status', 'ASC')->get();
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
        $user->kyc_details=Kyc::where('user_id', $user->id)->with('country')->with('documentType')->latest()->get()->first();

        $account=UserBank::join('banks','banks.id', '=', 'user_banks.bank_id')
            ->where('user_banks.user_id', $user->id)
            ->get([
                'banks.name as bank_name',
                'user_banks.account_no'
            ])->toArray();

        if(!empty($account))
        {
            $user->bank_account=$account;
        }


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
            'uuid' => 'required'
        ]);

        if ($validator->fails())
            return $this->sendError('Error',$validator->errors(),422);

        $user=User::where('uuid', $data['uuid'])->get()->first();
        if(!$user){
            return $this->sendError("Account not found",[],400);
        }
        if($user->kyc_status == 1){
            return $this->sendError("Merchant account is already verified",[],400);
        }

        if($user->role_id == 1){
            // if(!$user->kyc){
            //     return $this->sendError("KYC details has not been uploaded",[],400);
            // }else{
            //     if(!$user->kyc->bvn){
            //         return $this->sendError("KYC does not contain bvn",[],400);
            //     }
            //     if(!$user->kyc->nin){
            //         return $this->sendError("KYC does not contain NIN",[],400);
            //     }
            // }
            // $providus = ProvidusService::generateReservedAccount($user->kyc->bvn, $user->merchant->business_name);
            // $account = new Account();
            // $account->user_id = $user->id;
            // $account->bank = 'providus';
            // $account->accountNumber = $providus->account_number;
            // $account->accountName = $providus->account_name;
            // $account->type = 'reserved';
            // $account->save();
        }else{
            return $this->sendError("Account is not a merchant profile",[],400);
        }

        $user->kyc_status = 1;
        $user->save();

        $data2['activity']="Account successfully activated";
        $data2['user_id']=Auth::user()->id;
        ActivityLog::createActivity($data2);

        return $this->successfulResponse([], 'Account successfully activated');
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

        return $this->successfulResponse([], 'Account successfully Deactivated');
    }

    /**
     * @OA\Post(
     ** path="/api/v1/admin/fund-wallet",
     *   tags={"Admin"},
     *   summary="Fund user wallet by admin",
     *   operationId="Fund user wallet by admin",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"amount","email"},
     *              @OA\Property( property="amount", type="string"),
     *              @OA\Property( property="email", type="string"),
     *              @OA\Property( property="reference", type="string"),
     *              @OA\Property( property="currency", type="string"),
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
    public function fundWallet(Request $request)
    {
        $this->validate($request, [
            'amount'=>'required|numeric|min:1',
            'email'=>'required|email',
            'reference'=>'nullable',
            'currency'=>'nullable'
        ]);

        $user=User::where('email', $request['email'])->first();
        if(!$user){
            return $this->sendError('User not found',[],400);
        }

        if($request['reference']){
            $ext = $request['reference'];
        }else{
            $ext = 'LP_'.Uuid::generate()->string;
        }

        if($request['currency']){
            $currency = $request['currency'];
        }else{
            $currency = 'naira';
        }

        DB::transaction(function () use ($user, $ext, $currency, $request){

            $transaction = new Transaction();
            $transaction->user_id = $user->id;
            $transaction->reference_no	= 'LP_'.Uuid::generate()->string;
            $transaction->tnx_reference_no	= $ext;
            $transaction->amount =$request['amount'];
            $balance = floatval($user->wallet->withdrawable_amount) + floatval($request['amount']);
            if($currency == 'dollar'){
                $balance = floatval($user->wallet->dollar) + floatval($request['amount']);
            }
            $transaction->balance = $balance;
            $transaction->type = 'credit';
            $transaction->merchant = 'admin';
            $transaction->status = 1;
            $transaction->currency = $currency;
            $transaction->save();

            WalletService::addToWallet($user->id, $request['amount'], $currency);

        });
        $sym = $currency == 'naira' ? '₦': '$';
        $content = "You have received {$sym}{$request['amount']} ";
        // SmsService::sendMail("Dear {$user->first_name},", $content, "Wallet Credit", $user->email);
        ZeptomailService::sendMailZeptoMail("Wallet Credit" ,$content, $user->email);
        $sms = SmsService::sendSms("Wallet Credit, $content", '234'.$user->phoneNumber);
        return $this->successfulResponse([], 'Wallet funded successfully');
    }

    /**
     * @OA\Post(
     ** path="/api/v1/admin/send-mail-to-user",
     *   tags={"Admin"},
     *   summary="Send message to user by admin",
     *   operationId="Send message to user by admin",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"email","subject","message"},
     *              @OA\Property( property="email", type="string"),
     *              @OA\Property( property="subject", type="string"),
     *              @OA\Property( property="message", type="string")
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
    public function sendMailToUser(Request $request)
    {
        $data = $this->validate($request, [
            'email'=>'required|string',
            'subject'=>'required|string',
            'message'=>'required|string'
        ]);

        $data['uuid'] = Uuid::generate()->string;
        $data['status']=2;
        $data['reply']="no-reply";
        $contact=ContactUs::create($data);

        $from="contact@leverpay.io";

        $html = "
            <p style='margin-bottom: 8px'>{$data['message']}</p>
            <h4 style='margin-bottom: 8px'>
                reply to :<a href='mailto:".$from."'>{$from}</a>
            </h4>
        ";

        //SmsService::sendMail($data['subject'], $html, "Message from LeverPay", $data['email']);
        ZeptomailService::sendMailZeptoMail("Message from LeverPay, ".$data['subject'], $html, $data['email']);

        return $this->successfulResponse($contact, 'Message successfully sent');
    }

    /**
     * @OA\Post(
     ** path="/api/v1/admin/total-delete",
     *   tags={"Admin"},
     *   summary="Total delete",
     *   operationId="Total delete",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"email"},
     *              @OA\Property( property="email", type="string")
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
    public function totalDelete(Request $request)
    {
        $this->validate($request,[
            'email'=>'required'
        ]);

        $user = User::where('email', $request['email'])->first();
        if(!$user){
            return $this->sendError('User not found',[],400);
        }

        Account::where('user_id', $user->id)->delete();
        Card::where('user_id', $user->id)->delete();
        Investment::where('user_id', $user->id)->delete();
        Invoice::where('user_id', $user->id)->delete();
        Invoice::where('merchant_id', $user->id)->delete();
        Kyc::where('user_id', $user->id)->delete();
        Merchant::where('user_id', $user->id)->delete();
        MerchantKeys::where('user_id', $user->id)->delete();
        TopupRequest::where('user_id', $user->id)->delete();
        Transaction::where('user_id', $user->id)->delete();
        Transfer::where('user_id', $user->id)->delete();
        Transfer::where('receiver_id', $user->id)->delete();
        UserBank::where('user_id', $user->id)->delete();
        Wallet::where('user_id', $user->id)->delete();
        ActivityLog::where('user_id', $user->id)->delete();

        $user->delete();
        return $this->successfulResponse([], 'User deleted');
    }


    /**
     * @OA\Get(
     ** path="/api/v1/admin/get-merchants-for-remittance",
     *   tags={"Admin"},
     *   summary="Get merchants for remittance",
     *   operationId="Get merchants for remittance",
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
    public function getMerchantAccount()
    {
        $merchants=User::join('merchants', 'merchants.user_id','=','users.id')
            ->join('wallets', 'wallets.user_id', '=', 'users.id')
            ->where('wallets.withdrawable_amount', '>', 0)
            ->get([
                'users.id',
                'users.uuid',
                'users.email',
                'merchants.business_name',
                'merchants.business_address',
                'merchants.business_phone',
                'users.first_name as contact_person',
                'users.phone as contact_person_phone',
                'wallets.withdrawable_amount'
            ]);

        $merchants = $merchants->filter(function($merchant)
        {
            $total_invoice=Invoice::where('merchant_id', $merchant->id)->where('status', 1)->sum('total');
            $amount_paid=Remittance::where('user_id', $merchant->id)->sum('amount');
            $last_remmited=Remittance::where('user_id', $merchant->id)->latest()->get()->first();

            $getCurrency=Wallet::where('user_id', $merchant->id)->get(['amount','dollar'])->first();

            if(($total_invoice-$amount_paid) > 0)
            {
                $merchant['currency']=($getCurrency->amount > 0)?"naira":"dollar";

                $merchant['total_revenue']=$total_invoice;
                $merchant['tota_remitted']=$amount_paid;
                $merchant['last_remitted']=isset($last_remmited->amount)?$last_remmited->amount:0;

                $merchant['total_unremitted']=floatval($total_invoice-$amount_paid);
                $merchant['date']=date('d/m/y');

                return true; // Include
            }
            else{
                return false; // Exclude
            }

        });

        return $this->successfulResponse($merchants, 'Machants list with account balance greater than zero successfully retrieved');
    }

    /**
     * @OA\Post(
     ** path="/api/v1/admin/create-new-voucher",
     *   tags={"Admin"},
     *   summary="Create new voucher",
     *   operationId="Create new voucher",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"code_no"},
     *              @OA\Property( property="code_no", type="string")
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
    public function createNewVocher(Request $request)
    {
        $data = $request->all();

        $validator = Validator::make($data, [
            'code_no' => 'required|unique:vouchers,code_no'
        ]);

        if($validator->fails())
        {
            return $this->sendError('Error',$validator->errors(),422);
        }

        $newVoucher=Voucher::create(['code_no'=>$data['code_no']]);

        return $this->successfulResponse($newVoucher, 'new voucher successfully created');
    }

    /**
     * @OA\Get(
     ** path="/api/v1/admin/get-all-vouchers",
     *   tags={"Admin"},
     *   summary="Get all vouchers",
     *   operationId="Get all voucher list",
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
    public function getAllVouchers()
    {
        $all=Voucher::all();
        return $this->successfulResponse($all, 'Voucher list successfully retrieved');
    }

    /**
     * @OA\Get(
     ** path="/api/v1/admin/get-active-voucher",
     *   tags={"Admin"},
     *   summary="Get active voucher",
     *   operationId="Get active voucher",
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
    public function getActiveVoucher()
    {
        $active=Voucher::where('status', 1)->get(['id','code_no']);
        return $this->successfulResponse($active, 'Voucher list successfully retrieved');
    }

    /**
     * @OA\Post(
     ** path="/api/v1/admin/schedule-merchant-for-payment",
     *   tags={"Admin"},
     *   summary="Add merchant to payment schedule list",
     *   operationId="Add merchant to payment schedule list",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"voucher_id","uuid","amount"},
     *              @OA\Property( property="voucher_id", type="string"),
     *              @OA\Property( property="uuid", type="string"),
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
    public function addToRemittance(Request $request)
    {
        $data = $request->all();

        $validator = Validator::make($data, [
            'voucher_id' => 'required',
            'uuid' => 'required',
            'amount' => 'required|numeric'
        ]);

        if($validator->fails())
        {
            return $this->sendError('Error',$validator->errors(),422);
        }

        $getMerchant=User::where('uuid',$data['uuid'])->get(['id'])->first();
        
        $account_no=sprintf('%010d', mt_rand(1111111111,99999999999));

        
        $remittance=Remittance::create([
            'user_id'=>$getMerchant->id,
            'voucher_id'=>$data['voucher_id'],
            'amount'=>$data['amount'],
            'account_no'=>$account_no
        ]);


        return $this->successfulResponse($remittance, 'Merchant successfully added to payment schedule list');
    }

    /**
     * @OA\Get(
     ** path="/api/v1/admin/get-payment-schedule-list/{codeno}",
     *   tags={"Admin"},
     *   summary="Get payment schedule list by voucher code no",
     *   operationId="Get payment schedule list by voucher code no",
     *
     * * * @OA\Parameter(
     *      name="code_no",
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
    public function getRemittanceByVoucherCode($code_no)
    {
        $checkpoint=Voucher::where('code_no',$code_no)->first();
        if(!$checkpoint)
            return $this->sendError("Invalid voucher codeno ".$code_no, [],400);

        $results=Remittance::join('vouchers', 'vouchers.id', '=', 'remittances.voucher_id')
            ->join('users', 'users.id', '=', 'remittances.user_id')
            ->join('merchants', 'merchants.user_id', '=', 'users.id')
            ->where('code_no', $code_no)
            ->get([
                'remittances.created_at',
                'users.id',
                'users.uuid',
                'users.email',
                'merchants.business_name',
                'merchants.business_address',
                'merchants.business_phone',
                'users.first_name as contact_person',
                'users.phone as contact_person_phone',
                'remittances.amount',
                'remittances.currency',
                'remittances.account_no',
                'remittances.status'
            ]);

        $results->transform(function ($result)
        {
            $result->status=($result->status==0)?"pending":(($result->status==1)?"completed":"cancel");
            return $result;
        });

        return $this->successfulResponse($results, $code_no." payment schedule list successfully retrieved");
    }

    /**
     * @OA\Post(
     ** path="/api/v1/admin/complete-remittance",
     *   tags={"Admin"},
     *   summary="Confirm and complete payment schedule list",
     *   operationId="Confirm and complete payment schedule list",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"uuid","voucher_id"},
     *              @OA\Property(property="uuid", type="string"),
     *              @OA\Property(property="voucher_id", type="string"),
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
    public function completeRemittance(Request $request)
    {
        $data = $request->all();

        $validator = Validator::make($data, [
            'uuid'=>'required',
            'voucher_id'=>'required'
        ]);

        if($validator->fails())
        {
            return $this->sendError('Error',$validator->errors(),422);
        }
        //$data['user_id']=[3,3];
        foreach($data['uuid'] as $userUUId)
        {
            $getUU=User::where('uuid', $userUUId)->get(['id'])->first();
            $userId=$getUU->id;
            $user = User::join('merchants','merchants.user_id','=','users.id')
                ->where('users.id', $userId)
                ->get(['users.email','users.phone','merchants.business_name'])
                ->first();

            $findRmtnce=Remittance::where('user_id', $userId)->where('voucher_id', $data['voucher_id'])->get()->first();
            $amount=$findRmtnce->amount;
            $currency=$findRmtnce->currency;

            $email=$user->email;
            $phoneNumber=$user->phone;
            $name=$user->business_name;


            WalletService::subtractFromWallet($userId, $amount, $currency);

            $findRmtnce->payment_date=date('Y-m-d h:i:s');
            $findRmtnce->status=1;
            $findRmtnce->save();

            //send mail and sms
            $sym = $currency == 'naira' ? '₦': '$';
            $content = "Dear {$name}, {$sym}{$amount} was paid to your account by leverpay.io";
            ZeptomailService::sendMailZeptoMail("Levrepay Payment Notification" ,$content, $email);
            SmsService::sendSms("Levrepay Payment Notification, $content", '234'.$phoneNumber);
        }

        return $this->successfulResponse([], 'Payment successfully completed');


    }

    /**
     * @OA\Get(
     ** path="/api/v1/admin/get-total-revenue-n-remittance",
     *   tags={"Admin"},
     *   summary="Get total revenue,remitted amount and uremitted",
     *   operationId="Get total revenue,remitted amount and uremitted",
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
    public function getTotalRevenue()
    {
        $total_revenue_naira=Invoice::where('status', 1)->where('currency', 'naira')->sum('total');
        $total_revenue_dollar=Invoice::where('status', 1)->where('currency', 'dollar')->sum('total');

        $total_remittance_naira=Remittance::where('status', 1)->where('currency', 'naira')->sum('amount');
        $total_remittance_dollar=Remittance::where('status', 1)->where('currency', 'dollar')->sum('amount');

        $response=[
            'naira'=>[
                'total_revenue'=>$total_revenue_naira,
                'total_remitted'=>$total_remittance_naira,
                'total_unremitted'=>($total_revenue_naira-$total_remittance_naira),
            ],
            'dollar'=>[
                'total_revenue'=>$total_revenue_dollar,
                'total_remitted'=>$total_remittance_dollar,
                'total_unremitted'=>($total_revenue_dollar-$total_remittance_dollar),
            ]
        ];

        return $this->successfulResponse($response, 'Process successfully completed');
    }
}
