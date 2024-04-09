<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Services\{QuickTellerService,WalletService,ZeptomailService};
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use App\Models\{BillPaymentPin,BillPaymentHistory,Wallet,ActivityLog,Transaction,QuickTellerDiscount};

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
        $allowedCategories = ["Utilities", "Cable TV", "Airtime and Data", "Mobile Recharge", "Internet Services", "Travel and Hotel","Airtel Data","Airtime Top-up","Event Tickets","Transport and Toll Payments"];
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
    return $data['BillerList']['Category'];
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
    
    $data = collect($data['PaymentItems']);

    $data->transform(function ($item) {
        $amountInKobo = intval($item['Amount']); // Amount in kobo
        $amountInNaira = $amountInKobo / 100; // Convert kobo to naira
        $item['Amount'] = $amountInNaira; // Update the amount
        // Add referenceNo at the top getTransaction
        $item['ReferenceNo'] = base64_encode("2176-".uniqid());
        // if(intval($item['BillerCategoryId'])!=3 and intval($item['BillerCategoryId'])!=4)
        // {
        //   $item['Amount']=$item['Amount']+80;
        // }
        return $item;
    });

    
    // Convert back to JSON
    //$json_with_reference = json_encode($data);

    return $data;
  }

  /**
   * @OA\Get(
   *     path="/api/v1/user/quickteller/get-biller-payment-items-by-amount",
   *     tags={"Quick Teller"},
   *     summary="Get biller payment items by amount",
   *     operationId="Get biller payment items  by amount",
   *
   *     @OA\Parameter(
   *         name="billerId",
   *         in="query",
   *         required=true,
   *         description="This is returned from get-billers-by-category-id as Id",
   *         @OA\Schema(type="string")
   *     ),
   *
   *    @OA\Parameter(
   *         name="amount",
   *         in="query",
   *         required=true,
   *         description="Amount to recharge",
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
  public function getBillerPaymentItemByAmount(Request $request)
  {
    if (!Auth::user()->id) {
      return $this->sendError('Unauthorized Access', [], 401);
    }

    $billerId = $request->query('billerId');
    $amount = $request->query('amount');

    if($amount < 50)
    {
      return response()->json('Invalid Amount', 422);
    }

    $targetAmount=$amount*100;

    $accessToken=QuickTellerService::generateAccessToken();
    if(empty($accessToken))
    {
      return response()->json('No token generated', 422);
    }

    $jsonData=QuickTellerService::billerPaymentItems($accessToken,$billerId);
    $data = json_decode($jsonData, true);

    $closestItem = null;
    $closestDifference = PHP_INT_MAX;
    
    foreach ($data['PaymentItems'] as $item) 
    {
      if (isset($item['Amount'])) {
          $itemAmount = intval($item['Amount']);
          $difference = $targetAmount - $itemAmount;
          if ($itemAmount <= $targetAmount && $difference < $closestDifference) {
              $item['ReferenceNo'] = base64_encode("2176-" . uniqid());
              $item['Amount']= ($item['Amount']/100);
              $closestItem = $item;
              $closestDifference = $difference;
          }
      }
    }
    
    //$paymentItem = collect($closestItem['PaymentItems']);

    if ($closestItem) {
        return response()->json([$closestItem, 'Biller Payment Item By Amount']);
    } else {
        return response()->json('No matching item found for the given amount.', 422);
    }
      
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
    *              required={"customerId","amount","paymentCode","billerCategoryId","billerName","itemName","customerEmail","customerMobile","refrenceNo","pin"},
    *              @OA\Property( property="customerId", type="string", description="e.g Phone Number or Meter Token"),
    *              @OA\Property( property="amount", type="string", description="amount to acquire service"),
    *              @OA\Property( property="paymentCode", type="string", description="This is returned from get-biller-payment-items and it should be hidden"),
    *              @OA\Property( property="itemName", type="string", description="This is returned from get-biller-payment-items as Name. The field should be hidden"),
    *              @OA\Property( property="billerName", type="string", description="This is returned from get-biller-payment-items as BillerName  and it should be hidden"),
    *              @OA\Property( property="billerCategoryId", type="string", description="This is returned from get-biller-payment-items as BillerCategoryId  and it should be hidden"),
    *              @OA\Property( property="customerEmail", type="string", description="Customer Email"),
    *              @OA\Property( property="customerMobile", type="string", description="Customer Phone Number"),
    *              @OA\Property( property="refrenceNo", type="string", description="This is returned from get-biller-payment-items and it should be hidden"),
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
        'itemName' => 'required',
        'billerName' => 'required',
        'billerCategoryId'=>'required',
        'refrenceNo' => 'required', 
        'pin' => 'required|numeric'
    ]);

    if($validator->fails())
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
    $billerName=$data['billerName'];
    $billerCategoryId=$data['billerCategoryId']; 
    $itemName=$data['itemName']; 
    $amount=$data['amount'];
    $amount2=$amount*100; //conver it to kobo
    $refrenceNo=base64_decode($data['refrenceNo']);
    $charges=0;
    if($billerCategoryId !=3 and $billerCategoryId !=4 and $billerCategoryId !=63)
    {
      $charges=100;
    }

    $tAmount=($amount+$charges);
    
    $checkPin = $this->checkPinValidity($userId, $data['pin']);
    if (!$checkPin) {
      return response()->json('Invalid pin', 422);
    }

    $checkRefNo = $this->checkReferenceNoValidity($refrenceNo);
    if ($checkRefNo) {
        return response()->json('Duplicate Transactions', 422);
    }
    
    $checkBalance = $this->checkWalletBalance($userId, $tAmount);
    if (!$checkBalance) {
      return response()->json('Insufficient wallet balance', 422);
    }

    $getLeverPayAccount = $this->getLeverPayAccount(); 
    if (!$getLeverPayAccount->balance) {
      return response()->json('Transaction Failed, Add at least one leverpay account', 422);
    }

    $cashBack=0;
    $sCshBk=QuickTellerDiscount::where('biller', $billerName)->get(['percent'])->first();
    if($sCshBk)
    {
      $cashBack=round(($sCshBk->percent/(100))*$amount);
    }
    

    $newBalance=($getLeverPayAccount->balance + $amount)-$cashBack;

    $jsonData=QuickTellerService::sendBillPayment($accessToken,$paymentCode,$customerId,'development@leverpay.io',$customerMobile,$amount2,$refrenceNo);
    $responseData = json_decode($jsonData, TRUE);

    //return $responseData;
    if($responseData['ResponseCode'] !="90000")
    {
      return response()->json('Transaction Failed, '.$responseData['ResponseDescription'], 422);
    }

    $nin=[
      'referenceNo'=>$refrenceNo,
      'customerId'=>$customerId,
      'amount'=>$amount,
      'cash_back'=>$cashBack,
      'paymentCode'=>$paymentCode,
      'billerName' =>  $billerName,
      'billerCategoryId'=>$billerCategoryId,
      'itemName' =>  $itemName,
      'customerEmail'=>$customerEmail,
      'customerMobile'=>$customerMobile,
      'msg' => !empty($responseData['RechargePIN'])?$responseData['RechargePIN']:'',
      'transaction_fee'=>$charges
    ];
    DB::beginTransaction();
    try{

      $this->performTransaction($userId, $nin, $newBalance, $cashBack);
      DB::commit();
      if($cashBack > 0)
      {
        $msg="Your transaction was successful and Leverpay has given you a  (Cashback Reward of: #".number_format($cashBack,2).")";
        //$msg="Your transaction was successful";
      }
      else{
        $msg="Your transaction was successful";
      }

      $message=[
        'date'=>date('d M, Y'),
        'amount'=>$nin['amount'],
        'user_firstname'=>$user->first_name,
        'phone_number'=>$nin['customerId'],
        'token number'=>$nin['msg'],
        'Successful'=>'Successful',
        'transaction_ref'=>$nin['referenceNo'],
        'transaction_fee'=>$charges,
        'total_amount'=>$tAmount,
        'service_provider'=>$billerName
      ];
      //send mail
    
      ZeptomailService::sendTemplateZeptoMail("2d6f.117fe6ec4fda4841.k1.27b39400-ae24-11ee-a9af-525400103106.18ce91d9940" ,$message, $customerEmail);

      $result = [
        'message'=>$msg,
        'reference' => $nin['referenceNo'], 
        'cashback'=>$cashBack,
        'token'=>$nin['msg'],
        'transaction_fee'=>$nin['transaction_fee']
      ];
      //$customerEmail
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
    return BillPaymentHistory::where('transaction_reference', $reference_no)->first();
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
    $wBal=($nin['amount']-$cashBack)+$nin['transaction_fee'];
    $new_user_wall=$getOldBal->withdrawable_amount-$wBal;
    WalletService::subtractFromWallet($userId, $wBal, 'naira');

    $newBalance=$newBalance+$nin['transaction_fee'];

    DB::table('lever_pay_account_no')->where('id', 3)->update(['balance' => $newBalance]);
    
    BillPaymentHistory::create([
        'user_id' => $userId,
        'customerId' => $nin['customerId'],
        'unit_purchased' => 0,
        'price' => $wBal,
        'amount' => $wBal,
        'cash_back'=>$cashBack,
        'category' => $nin['billerName'],
        'biller' => '-',
        'product' => '-',
        'item' => $nin['itemName'],
        'extra' => $extra,
        'provider_name' => 'QUICK TELLER',
        'transaction_reference' => $nin['referenceNo'],
    ]);

    $details = json_encode([
        "customerId"=>$nin['customerId'],
        "customerMobile"=>$nin['customerMobile'],
        "customerEmail"=>$nin['customerEmail'],
        "amount"=>$nin['amount'],
        "billerName"=>$nin['billerName'],
        "itemName"=>$nin['itemName'],
        "paymentCode"=>$nin['paymentCode'],
        "provider"=>"Quick Teller",
        "token"=>$nin['msg'],
        "transaction_fee"=>$nin['transaction_fee']
    ]);

    Transaction::create([
        'user_id' =>  $userId,
        'reference_no' => $nin['referenceNo'],
        'tnx_reference_no' => $nin['referenceNo'],
        'amount' => $wBal,
        'balance' => $new_user_wall,
        'type' => 'debit',
        'merchant' => 'Bills',
        'status' => 1,
        'transaction_details' => $details
    ]);

    $activity['activity']="Quick Teller bill payment of ".$nin['itemName']." for N".$wBal;
    $activity['user_id']=$userId;

    ActivityLog::createActivity($activity);

  }

  /**
   * @OA\Get(
   *     path="/api/v1/user/quickteller/get-customer-transaction",
   *     tags={"Quick Teller"},
   *     summary="Get customer transaction by refNo",
   *     operationId="Get customer transaction by refNo",
   *
   *     @OA\Parameter(
   *         name="requestRef",
   *         in="query",
   *         required=true,
   *         description="transaction reference no",
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
  public function getTransaction(Request $request)
  {
    if (!Auth::user()->id) {
      return $this->sendError('Unauthorized Access', [], 401);
    }

    $requestRef = $request->query('requestRef');

    $accessToken=QuickTellerService::generateAccessToken();
    if(empty($accessToken))
    {
      return response()->json('No token generated', 422);
    }

    $jsonData=QuickTellerService::getTransaction($accessToken,$requestRef);

    $data = json_decode($jsonData, true);

    return $data;
  }
}
