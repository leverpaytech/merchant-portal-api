<?php

namespace App\Http\Controllers\Merchant;
use App\Http\Controllers\BaseController;
use App\Http\Controllers\Controller;
use App\Mail\GeneralMail;
use App\Mail\SendEmailVerificationCode;
use App\Models\Transaction;
use App\Models\User;
use App\Services\SmsService;
use App\Services\ZeptomailService;
use App\Services\WalletService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Models\ActivityLog;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Webpatser\Uuid\Uuid;
use Carbon\Carbon;

class InvoiceController extends BaseController
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
    */
    /**
     * @OA\Post(
     ** path="/api/v1/merchant/create-invoice",
     *   tags={"Merchant"},
     *   summary="Create a new invoice",
     *   operationId="create new invoice",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"product_name","price","vat","email","currency"},
     *              @OA\Property( property="product_name", type="string"),
     *              @OA\Property( property="product_description", type="string"),
     *              @OA\Property( property="price", type="string"),
     *              @OA\Property( property="vat", type="string"),
     *              @OA\Property( property="currency", type="string"),
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
    public function createInvoice(Request $request){
        $data = $this->validate($request, [
            'product_name'=>'required|string',
            'product_description'=>'nullable|string',
            'price'=>'required|numeric',
            // 'product_image' => 'nullable|mimes:jpeg,png,jpg,gif|max:2048',
            //'quantity'=>'required|numeric|min:1',
            'email'=>'required|email',
            'vat'=>'required|numeric|min:0',
            'currency'=>'required|string'
        ]);
        $data['product_description'] = $request['product_description'];

        $user = User::where('email', $data['email'])->first();
        // if(!$user)
        // {
        //     return $this->sendError("The provided email address is not registered with leverpay.io",[],400);
        // }
        $firstname = '';
        if($user){
            $data['user_id'] = $user->id;
            $data['type'] = 1;
            $firstname= $user->firstname;
        }

        $uuid = Uuid::generate()->string;

        $cal = $request['price'] + ($request['price'] * ($request['vat'] / 100));

        $currency = ExchangeRate::where('status', 1)->latest()->first();

        $data['currency']=strtolower($data['currency']);

        $sym = '₦';
        if($data['currency'] == 'dollar'){
            $sym = '$';
            $fee = $request['price'] * ($currency->international_transaction_rate / 100);
        }else if($data['currency'] == 'naira'){
            $fee = $request['price'] * ($currency->local_transaction_rate / 100);
        }else{
            return $this->sendError('Invalid currency',[],400);
        }
        $data['uuid'] = $uuid;
        $merchantId=Auth::user()->id;
        $data['merchant_id']=$merchantId;
        //$data['url'] = env('CHECKOUT_BASE_URL').'/invoice/'.$uuid;
        $data['url'] = 'https://checkout-page-lyart-ten.vercel.app/'.$uuid;
        $data['total'] = $cal + $fee;
        $data['fee'] = $fee;

        $getMarchant=User::where('id', $merchantId)->with('merchant')->get()->first();
        $merchant_business_name=$getMarchant->merchant->business_name;

        DB::transaction( function() use($data, $merchantId) {

            Invoice::create($data);

            $data2['activity']="Create Invoice,  ".$data['uuid'];
            $data2['user_id']=$merchantId;
            ActivityLog::createActivity($data2);

        });

        $invoice = Invoice::where('uuid', $data['uuid'])->first();
        $vat_cal=(($data['vat']/100)*$data['price']);

        //sent create invoice notification to user

        $message=[
            'customer_name'=>$data['email'],
            'merchant_name'=>$merchant_business_name,
            'product_name'=>$data['product_name'],
            'description'=>$data['product_description'],
            'amount'=>$sym.$data['price'],
            'transaction_fee'=>$data['fee'],
            'total'=>$data['total'],
            'view_invoice_link'=>$data['url'],
            'vat'=>$vat_cal
        ];

        // ZeptomailService::payInvoiceMail("Invoice notification" ,$message, $data['email']);
        ZeptomailService::sendTemplateZeptoMail("2d6f.117fe6ec4fda4841.k1.d46e9be0-9b7d-11ee-a277-5254004d4100.18c6ee4949e" ,$message, $data['email']);
        return $this->successfulResponse($invoice,"Invoice successfully created");

    }


    /**
     * @OA\Get(
     ** path="/api/v1/merchant/product/{uuid}",
     *   tags={"Merchant"},
     *   summary="Get invoice by product uuid",
     *   operationId="get invoice by product uuid",
     *
     * * @OA\Parameter(
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
    public function getInvoice($uuid)
    {
        $invoice = Invoice::query()->where('uuid',$uuid)->with(['merchant' => function ($query) {
            $query->select('id','uuid', 'first_name','last_name','phone','email')->with('merchant');
        }])->with(['user' => function ($query) {
            $query->select('id','uuid', 'first_name','last_name','phone','email');
        }])->first();

        if(!$invoice){
            return $this->sendError('Invoice not found',[],400);
        }
        return $this->successfulResponse($invoice, 'Invoice successfully retrieved');
    }

    /**
     * @OA\Get(
     ** path="/api/v1/user/get-invoices",
     *   tags={"User"},
     *   summary="Get all invoices",
     *   operationId="get all invoices",
     *
     * * * @OA\Parameter(
     *      name="status",
     *      in="path",
     *      required=false,
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

    public function getInvoices(Request $request)
    {
        $invoices = Auth::user()->invoices();

        $filter = strval($request->query('status'));

        if($filter == 'pending'){
            $invoices = $invoices->where('status', 0)
                ->with(['merchant' => function ($query) {
                    $query->select('id','uuid', 'first_name','last_name','phone','email')->with('merchant');
                }])->with(['user' => function ($query) {
                    $query->select('id','uuid', 'first_name','last_name','phone','email');
                }])->get();

        }else if($filter == 'paid'){
            $invoices = $invoices->where('status', 1)
                ->with(['merchant' => function ($query) {
                    $query->select('id','uuid', 'first_name','last_name','phone','email')->with('merchant');
                }])->with(['user' => function ($query) {
                    $query->select('id','uuid', 'first_name','last_name','phone','email');
                }])->get();
        }else if($filter == 'cancelled'){
            $invoices = $invoices->where('status', 2)
                ->with(['merchant' => function ($query) {
                    $query->select('id','uuid', 'first_name','last_name','phone','email')->with('merchant');
                }])->with(['user' => function ($query) {
                    $query->select('id','uuid', 'first_name','last_name','phone','email');
                }])->get();
        }else{
            $invoices = $invoices->with(['merchant' => function ($query) {
                    $query->select('id','uuid', 'first_name','last_name','phone','email')->with('merchant');
                }])->with(['user' => function ($query) {
                    $query->select('id','uuid', 'first_name','last_name','phone','email');
                }])->get();
        }

        return $this->successfulResponse($invoices, '');
    }

    /**
     * @OA\Post(
     ** path="/api/v1/user/pay-invoice",
     *   tags={"User"},
     *   summary="Pay invoice",
     *   operationId="Pay new invoice",
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
    public function payInvoice(Request $request){
        $this->validate($request, [
            'uuid'=>'required|string'
        ]);

        $invoice = Invoice::where('uuid', $request->uuid)->first();
        if(!$invoice){
            return $this->sendError("Invoice not found",[],400);
        }

        if($invoice->status != 0){
            return $this->sendError("Invoice is already processed",[],400);
        }

        if($invoice->user_id != Auth::id()){
            return $this->sendError("Invoice is not assigned to you",[],400);
        }

        $total = 0;
        $dollar = false;
        if($invoice->currency == 'dollar'){
            $rate = ExchangeRate::latest()->first();
            if($invoice->user->wallet->dollar < $invoice->total){
                $total = floatval($invoice->total) * floatval($rate->rate);
            }else{
                $total = $invoice->total;
                $dollar = true;
            }
        }else{
            $total = $invoice->total;
        }

        if($dollar){
            if($invoice->user->wallet->dollar < $total){
                return $this->sendError("Insufficient dollar balance",[],400);
            }
        }else{
            if($invoice->user->wallet->withdrawable_amount < $total){
                return $this->sendError("Insufficient balance",[],400);
            }
        }

        $otp = rand(1000, 9999);
        $invoice->otp = $otp;
        $invoice->save();

        $curr = $invoice['currency'] == 'dollar'?'$':'₦';
        $content="A request to pay an invoice of  {$curr}{$invoice['total']} has been made on your account, to verify your otp is: <br /> {$otp}";

        // ZeptomailService::sendMailZeptoMail("Dear {$invoice->user->first_name}," ,$content, $invoice->email);
        $msg = [
            'otp' => $otp,
            'name'=>$invoice->user->first_name
        ];
        ZeptomailService::sendTemplateZeptoMail("2d6f.117fe6ec4fda4841.k1.1e37fec0-b563-11ee-8d93-525400e3c1b1.18d189a48ac",$msg, $invoice->email);

        SmsService::sendSms("Dear {$invoice->user->first_name},A request to pay an invoice of  {$curr}{$invoice['total']} has been made on your account, to verify your One-time Confirmation code is {$otp} and it will expire in 10 minutes. Please do not share For enquiry: contact@leverpay.io", '234'.$invoice->user->phone);

        return $this->successfulResponse([], 'OTP sent');
    }

    /**
     * @OA\Post(
     ** path="/api/v1/user/verify-invoices-otp",
     *   tags={"User"},
     *   summary="Verify invoice otp",
     *   operationId="Verify invoice otp",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"uuid","otp"},
     *              @OA\Property( property="uuid", type="string"),
     *              @OA\Property( property="otp", type="string"),
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
    public function verifyInvoiceOTP(Request $request){
        $this->validate($request, [
            'uuid'=>'required|string',
            'otp'=>'required|numeric'
        ]);

        $invoice = Invoice::where('uuid', $request->uuid)->first();
        if(!$invoice){
            return $this->sendError("Invoice not found",[],400);
        }

        if($invoice->status != 0){
            return $this->sendError("Invoice is already processed",[],400);
        }

        if($request['otp'] != $invoice->otp){
            return $this->sendError("Invalid otp, please try again",[],400);
        }

        $total = 0;
        $dollar = false;
        $rate = [];
        if($invoice->currency == 'dollar'){

            if($invoice->user->wallet->dollar < $invoice->total){
                $rate = ExchangeRate::latest()->first();
                $total = floatval($invoice->total) * floatval($rate->rate);
            }else{
                $total = $invoice->total;
                $dollar = true;
            }
        }else{
            $total = $invoice->total;
        }

        if($dollar){
            if($invoice->user->wallet->dollar < $total){
                return $this->sendError("Insufficient dollar balance",[],400);
            }
        }else{
            if($invoice->user->wallet->withdrawable_amount < $total){
                return $this->sendError("Insufficient balance",[],400);
            }
        }

        DB::transaction( function() use($invoice, $dollar, $total, $rate) {
            $ext = 'LP_'.Uuid::generate()->string;
            $transaction = new Transaction();
            $transaction->user_id = $invoice->user_id;
            $transaction->reference_no	= 'LP_'.Uuid::generate()->string;
            $transaction->tnx_reference_no	= $ext;
            $transaction->amount =$invoice->total;
            $transaction->balance = floatval($invoice->user->wallet->withdrawable_amount) - floatval($invoice->total);
            $transaction->type = 'debit';
            $transaction->merchant = 'invoice';
            $transaction->status = 1;
            if($invoice->currency == 'dollar'){
                $transaction->currency = 'dollar';
            }
            $details = [
                "invoice_uuid"=>$invoice->uuid,
                "exchange_rate"=>$rate
            ];
            if(!$dollar){
                $details2['is_convert']=$dollar;
                $details2['after_convert_total'] = $total;
            }
            $transaction->transaction_details = json_encode($details);
            $transaction->save();

            $transaction2 = new Transaction();
            $transaction2->user_id = $invoice->merchant_id;
            $transaction2->reference_no	= $ext;
            $transaction2->tnx_reference_no	= Uuid::generate()->string;
            $transaction2->amount =$invoice->total;
            $transaction2->balance = floatval($invoice->merchant->wallet->withdrawable_amount) + floatval($invoice->total);
            $transaction2->type = 'credit';
            $transaction2->merchant = 'invoice';
            $transaction2->status = 1;
            if($invoice->currency == 'dollar'){
                $transaction2->currency = 'dollar';
            }
            $details2 = [
                "invoice_uuid"=>$invoice->uuid,
                "exchange_rate"=>$rate,
            ];
            if(!$dollar){
                $details2['is_convert']=$dollar;
                $details2['after_convert_total'] = $total;
            }
            $transaction2->transaction_details = json_encode($details2);
            $transaction2->save();

            $curr = $dollar ? 'dollar' :'naira';
            WalletService::addToWallet($invoice->merchant_id, $total,$curr);
            WalletService::subtractFromWallet($invoice->user_id, $total, $curr);

            $invoice->status = 1;
            $invoice->save();
            $html="<p style='margin-bottom: 8px'>Dear {$invoice->user->first_name},</p><p style='margin-bottom: 10px'>You have successfully paid an invoice of $total to {$invoice->merchant->first_name} {$invoice->merchant->last_name}</p><p> Best regards, </p><p> Leverpay </p>";

            $html2="<p style='margin-bottom: 8px'>Dear {$invoice->merchant->first_name},</p><p style='margin-bottom: 10px'>An invoice of $total sent to {$invoice->user->first_name} {$invoice->merchant->last_name} has been paid</p><p> Best regards, </p><p> Leverpay </p>";
            //mail to user
            ZeptomailService::sendMailZeptoMail("Invoice Completed" ,$html, $invoice->user->email);
            //mail to merchant
            ZeptomailService::sendMailZeptoMail("Invoice Completed" ,$html2, $invoice->merchant->email);
            //SmsService::sendMail('', $html, 'Invoice Completed', $invoice->user->email);
            //SmsService::sendMail('', $html2, 'Invoice Completed', $invoice->merchant->email);
        });

        return $this->successfulResponse([], 'Invoice paid successfully');
    }

        /**
     * @OA\Get(
     ** path="/api/v1/merchant/get-invoices",
     *   tags={"Merchant"},
     *   summary="Get all merchants invoices",
     *   operationId="get all merchants invoices",
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
    public function getMerchantInvoices(Request $request){
        $invoices = Invoice::where('merchant_id', Auth::id());

        $filter = strval($request->query('status'));

        if($filter == 'pending'){
            $invoices = $invoices->where('status', 0)->get();
        }else if($filter == 'paid'){
            $invoices = $invoices->where('status', 1)->get();
        }else if($filter == 'cancelled'){
            $invoices = $invoices->where('status', 2)->get();
        }else{
            $invoices = $invoices->get();
        }

        return $this->successfulResponse($invoices, '');
    }

    /**
     * @OA\Get(
     ** path="/api/v1/merchant/get-merchant-total-transactions",
     *   tags={"Merchant"},
     *   summary="Get merchant total transactions (monthly, weekly and daily)",
     *   operationId="Get merchant total transactions (monthly, weekly and daily)",
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
    public function getMerchantTransaction()
    {
        $user_id=Auth::user()->id;

        $monthlyDollar=Invoice::where('merchant_id', $user_id)
            ->where('currency', 'dollar')
            ->whereMonth('created_at', Carbon::now()->month)
            ->sum('total');

        $weeklyDollar=Invoice::where('merchant_id', $user_id)
            ->where('currency', 'dollar')
            ->whereBetween('created_at',
                [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]
            )
            ->sum('total');

        $dailyDollar=Invoice::where('merchant_id', $user_id)
            ->where('currency', 'dollar')
            ->whereDate('created_at', Carbon::now()->format('Y-m-d'))
            ->sum('total');

        $monthlyNaira=Invoice::where('merchant_id', $user_id)
            ->where('currency', 'naira')
            ->whereMonth('created_at', Carbon::now()->month)
            ->sum('total');

        $weeklyNaira=Invoice::where('merchant_id', $user_id)
            ->where('currency', 'naira')
            ->whereBetween('created_at',
                [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]
            )->sum('total');

        $dailyNaira=Invoice::where('merchant_id', $user_id)
            ->where('currency', 'naira')
            ->whereDate('created_at', Carbon::now()->format('Y-m-d'))
            ->sum('total');

        $totalTransaction=[
            'dollar'=>[
                'monthly'=>$monthlyDollar,
                'weekly'=>$weeklyDollar,
                'daily'=>$dailyDollar
            ],
            'naira'=>[
                'monthly'=>$monthlyNaira,
                'weekly'=>$weeklyNaira,
                'daily'=>$dailyNaira
            ]
        ];

        return $this->successfulResponse($totalTransaction, "merchant total transactions successfully retrieved");

    }

    /**
     * @OA\Get(
     ** path="/api/v1/merchant/merchant-total-successfull-failed-transactions",
     *   tags={"Merchant"},
     *   summary="Get merchant total successfull and failed transactions",
     *   operationId="Get merchant total successfull and failed transactions",
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
    public function getTotalTransactions()
    {
        $user_id=Auth::user()->id;

        $success=Invoice::where('merchant_id', $user_id)
            ->where('status', 1)
            ->count();

        $failed=Invoice::where('merchant_id', $user_id)
            ->where('status', 2)
            ->count();

        $totalTransaction=[
            'total_successful_transactions'=>$success,
            'total_failed_transactions'=>$failed
        ];

        return $this->successfulResponse($totalTransaction, "merchant total transactions successfully retrieved");

    }

    /**
     * @OA\Get(
     ** path="/api/v1/user/invoice-detatails/{uuid}",
     *   tags={"User"},
     *   summary="Get user invoice details by product uuid",
     *   operationId="get user invoice details by product uuid",
     *
     * * @OA\Parameter(
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
    public function getUserInvoiceByUuid($uuid)
    {
        $invoice = Invoice::query()->where('uuid',$uuid)->with(['merchant' => function ($query) {
            $query->select('id','uuid', 'first_name','last_name','phone','email')->with('merchant');
        }])->with(['user' => function ($query) {
            $query->select('id','uuid', 'first_name','last_name','phone','email');
        }])->first();

        if(!$invoice){
            return $this->sendError('Invoice not found',[],400);
        }
        return $this->successfulResponse($invoice, 'Invoice successfully retrieved');
    }

    /**
     * @OA\Get(
     ** path="/api/v1/merchant/merchant-revenue-generated",
     *   tags={"Merchant"},
     *   summary="Get merchant revenue generated details",
     *   operationId="get merchant revenue generated details",
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
    public function getMerchantRevenue()
    {
        $user_id=Auth::user()->id;

        $total_invoice=Invoice::where('merchant_id', $user_id)->where('status', 1)->sum('total');
        $amount_paid=Remittance::where('user_id', $user_id)->sum('amount');
        $last_remmited=Remittance::where('user_id', $user_id)->latest()->get()->first();

        $getCurrency=Wallet::where('user_id', $user_id)->get(['amount','dollar'])->first();

        $totalRevenue['currency']=($getCurrency->amount > 0)?"naira":"dollar";
        $totalRevenue['total_revenue']=$total_invoice;
        $totalRevenue['tota_remitted']=$amount_paid;
        $totalRevenue['last_remitted']=isset($last_remmited->amount)?$last_remmited->amount:0;

        $totalRevenue['total_unremitted']=($total_invoice-$amount_paid)>0?floatval($total_invoice-$amount_paid):0;


        return $this->successfulResponse($totalRevenue, 'Total revenue generated');
    }
}
