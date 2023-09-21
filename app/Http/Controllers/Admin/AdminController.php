<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\BaseController;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\{User,Kyc};
use Illuminate\Http\Request;
use Webpatser\Uuid\Uuid;
use App\Models\PaymentOption;
use App\Http\Resources\PaymentOptionResource;
use Illuminate\Support\Facades\Auth;

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
        return $this->successfulResponse(User::where('role_id','0')->get(), 'Users List');
    }

    /**
     * @OA\Get(
     ** path="/api/v1/admin/get-kyc-list",
     *   tags={"KYC"},
     *   summary="Get all KYC details",
     *   operationId="Get all KYC",
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
    public function getKycs()
    {
        $kycs=Kyc::orderBy('status', 'DESC')->get();
        
        return $this->successfulResponse($kycs, 'kyc details successfully retrieved');

    }

}
