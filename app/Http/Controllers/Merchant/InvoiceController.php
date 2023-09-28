<?php

namespace App\Http\Controllers\Merchant;
use App\Http\Controllers\BaseController;
use App\Http\Controllers\Controller;
use App\Mail\SendEmailVerificationCode;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Models\ActivityLog;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Webpatser\Uuid\Uuid;

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
     *              required={"product_name","price","vat","email",},
     *              @OA\Property( property="product_name", type="string"),
     *              @OA\Property( property="price", type="string"),
     *              @OA\Property( property="quantity", type="string"),
     *              @OA\Property( property="product_description", type="string"),
     *              @OA\Property( property="vat", type="string"),
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
            'email'=>'required|email',
            'vat'=>'required|numeric|min:0',
            'currency'=>'required|string'
        ]);

        $user = User::where('email', $data['email'])->first();
        if($user){
            $data['user_id'] = $user->id;
            $data['type'] = 1;
        }

        $uuid = Uuid::generate()->string;

        $cal = $request['price'] + ($request['price'] * ($request['vat'] / 100));

        $currency = ExchangeRate::where('status', 1)->latest()->first();


        if($data['currency'] == 'dollar'){
            $fee = $request['price'] * ($currency->international_transaction_rate / 100);
        }else if($data['currency'] == 'naira'){
            $fee = $request['price'] * ($currency->local_transaction_rate / 100);
        }else{
            return $this->sendError('Invalid currency',[],400);
        }
        $data['uuid'] = $uuid;
        $merchantId=Auth::user()->id;
        $data['merchant_id']=$merchantId;
        $data['url'] = env('FRONTEND_BASE_URL').'/invoice/'.$uuid;
        $data['total'] = $cal + $fee;
        $data['fee'] = $fee;

        DB::transaction( function() use($data, $merchantId) {

            Invoice::create($data);

            $data2['activity']="Create Invoice,  ".$data['uuid'];
            $data2['user_id']=$merchantId;
            ActivityLog::createActivity($data2);

        });

        $invoice = Invoice::where('uuid', $data['uuid'])->first();

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
            $query->select('id','uuid', 'first_name','last_name','phone');
        }])->with(['user' => function ($query) {
            $query->select('id','uuid', 'first_name','last_name','phone');
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
        $invoices = Auth::user()->invoices();

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
     ** path="/api/v1/merchant/get-invoices",
     *   tags={"merchant"},
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
}
