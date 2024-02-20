<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Services\QuickTellerService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use App\Models\{BillPaymentPin,BillPaymentHistory,Wallet,ActivityLog,Transaction};

class QuickTellerController extends BaseController
{
  /**
   * @OA\Get(
   *     path="/api/v1/user/quickteller/get-billers",
   *     tags={"Quick Teller"},
   *     summary="Get billers",
   *     operationId="Get billers",
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
  public function getBillers()
  {
    $accessToken=QuickTellerService::generateAccessToken();
    if(empty($accessToken))
    {
      return response()->json('No token generated', 422);
    }

    return QuickTellerService::billers($accessToken);  
  }

  /**
   * @OA\Get(
   *     path="/api/v1/user/quickteller/get-billers-categories",
   *     tags={"Quick Teller"},
   *     summary="Get billers categories",
   *     operationId="Get billers categories",
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
  public function getBillersCategories()
  {
    $accessToken=QuickTellerService::generateAccessToken();
    if(empty($accessToken))
    {
      return response()->json('No token generated', 422);
    }
    $jsonData=QuickTellerService::billersCategories($accessToken);

    $data = json_decode($jsonData, true);

    // Filter the "BillerCategories" array based on category names
    $filteredCategories = array_filter($data['BillerCategories'], function($category) {
        $allowedCategories = ["Utility Bills", "Cable TV Bills", "Mobile/Recharge", "Subscriptions", "Airlines", "Transport"];
        return in_array($category['Name'], $allowedCategories);
    });
    
    $categories = [];
    foreach ($filteredCategories as $item) {
        $categories[] = $item;
    }
    return $categories;   
  }

  /**
   * @OA\Get(
   *     path="/api/v1/user/quickteller/get-billers-by-category-id",
   *     tags={"Quick Teller"},
   *     summary="Get billers by category id",
   *     operationId="Get billers by category id",
   *
   *     @OA\Parameter(
   *         name="categoryId",
   *         in="query",
   *         required=true,
   *         description="This is returned from get-billers-categories as Id",
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
  public function getBillersCategoryId(Request $request)
  {
    if (!Auth::user()->id) {
      return $this->sendError('Unauthorized Access', [], 401);
    }

    $categoryId = $request->query('categoryId');

    $accessToken=QuickTellerService::generateAccessToken();
    if(empty($accessToken))
    {
      return response()->json('No token generated', 422);
    }

    $jsonData=QuickTellerService::billersByCategoryId($accessToken,$categoryId);

    $data = json_decode($jsonData, true);
    // Access the Category
    $category = $data['BillerList']['Category'];
    //$categoryJson = json_encode($category);

    return $category;
  }

  /**
   * @OA\Get(
   *     path="/api/v1/user/quickteller/get-biller-payment-items",
   *     tags={"Quick Teller"},
   *     summary="Get biller payment items",
   *     operationId="Get biller payment items",
   *
   *     @OA\Parameter(
   *         name="billerId",
   *         in="query",
   *         required=true,
   *         description="This is returned from get-billers-by-category-id as Id",
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
  public function getBillerPaymentItems(Request $request)
  {
    if (!Auth::user()->id) {
      return $this->sendError('Unauthorized Access', [], 401);
    }

    $billerId = $request->query('billerId');

    $accessToken=QuickTellerService::generateAccessToken();
    if(empty($accessToken))
    {
      return response()->json('No token generated', 422);
    }

    $jsonData=QuickTellerService::billerPaymentItems($accessToken,$billerId);

    $data = json_decode($jsonData, true);

    // Add referenceNo at the top
    $data['PaymentItems']['ReferenceNo'] = base64_encode("Leverpay-".uniqid());;

    // Convert back to JSON
    //$json_with_reference = json_encode($data);

    return $data;
  }

  /**
 * @OA\Post(
 ** path="/api/v1/user/quickteller/validate-customer",
  *   tags={"Quick Teller"},
  *   summary="Validate Customer",
  *   operationId="Validate Customer",
  *
  *    @OA\RequestBody(
  *      @OA\MediaType( mediaType="multipart/form-data",
  *          @OA\Schema(
  *              required={"customerId","paymentCode"},
  *              @OA\Property( property="customerId", type="string"),
  *              @OA\Property( property="paymentCode", type="string"),
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
  public function validateCustomer(Request $request)
  {
    $data = $request->all();

    $validator = Validator::make($data, [
        'customerId' => 'required|string',
        'paymentCode' => 'required|numeric'
    ]);

    if ($validator->fails())
    {
        return $this->sendError('Error',$validator->errors(),422);
    }
    if (!Auth::user()->id) {
      return $this->sendError('Unauthorized Access', [], 401);
    }

    $billerId = $request->query('billerId');

    $accessToken=QuickTellerService::generateAccessToken();
    if(empty($accessToken))
    {
      return response()->json('No token generated', 422);
    }
    
    $result=QuickTellerService::validateCustomer($accessToken,$data['paymentCode'],$data['customerId']);
    return json_decode($result);
  }
  /**
   * @OA\Post(
   ** path="/api/v1/user/quickteller/submit-bill-payment",
    *   tags={"Quick Teller"},
    *   summary="Submit Bill Payment Advice",
    *   operationId="Submit Bill Payment Advice",
    *
    *    @OA\RequestBody(
    *      @OA\MediaType( mediaType="multipart/form-data",
    *          @OA\Schema(
    *              required={"customerId","amount","paymentCode","customerEmail","customerMobile","refrenceNo","pin"},
    *              @OA\Property( property="customerId", type="string", description="e.g Phone Number or Meter Token"),
    *              @OA\Property( property="amount", type="string", description="amount to acquire service"),
    *              @OA\Property( property="paymentCode", type="string", description="This is returned from get-biller-payment-items and it should be hidden"),
    *              @OA\Property( property="customerEmail", type="string", description="Customer Email"),
    *              @OA\Property( property="customerMobile", type="string", description="Customer Phone Number"),
    *              @OA\Property( property="refrenceNo", type="string", description="This is returned from get-biller-payment-items and it is returned from the get-biller-payment-items and it should be hidden"),
    *              @OA\Property( property="pin", type="string", description="bill payment pin"),
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
  public function sendBillPayment(Request $request)
  {
    $data = $request->all();
    
    $validator = Validator::make($data, [
        'customerId' => 'required|string',
        'amount' => 'required|numeric',
        'paymentCode' => 'required|string',
        'customerEmail' => 'required',
        'customerMobile' => 'required',
        'refrenceNo' => 'required', 
        'pin' => 'required|numeric'
    ]);

    if ($validator->fails())
    {
      return $this->sendError('Error',$validator->errors(),422);
    }

    $user = Auth::user();
    if(!$user->id)
      return $this->sendError('Unauthorized Access',[],401);
    
    $userId = $user->id;

    $accessToken=QuickTellerService::generateAccessToken();
    if(empty($accessToken))
    {
      return response()->json('No token generated', 422);
    }

    $paymentCode=$data['paymentCode'];
    $customerId=$data['customerId'];
    $customerEmail=$data['customerEmail'];
    $customerMobile=$data['customerMobile'];
    $amount=$data['amount'];
    $refrenceNo=base64_decode($data['refrenceNo']);

    $checkPin = $this->checkPinValidity($userId, $data['pin']);
    if (!$checkPin) {
      return response()->json('Invalid pin', 422);
    }

    /*$checkBalance = $this->checkWalletBalance($userId, $amount);
    if (!$checkBalance) {
      return response()->json('Insufficient wallet balance', 422);
    }

    $getLeverPayAccount = $this->getLeverPayAccount(); 
    if (!$getLeverPayAccount->balance) {
      return response()->json('Transaction Failed, Add at least one leverpay account', 422);
    }*/
        

    $jsonData=QuickTellerService::sendBillPayment($accessToken,$paymentCode,$customerId,$customerEmail,$customerMobile,$amount,$refrenceNo);

    return $jsonData;
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
          'merchant' => $nin['paymentItem'],
          'status' => 1,
          'transaction_details' => $details
      ]);

      $activity['activity']="vfd bill payment of ".$nin['paymentItem']." for N".$nin['amount'];
      $activity['user_id']=$userId;

      ActivityLog::createActivity($activity);

  }
}
