<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Webpatser\Uuid\Uuid;
use App\Models\PaymentOption;
use App\Http\Resources\PaymentOptionResource;

class AdminController extends Controller
{
    /**
     * @OA\Post(
     ** path="/api/admin/add-payment-option",
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
     *       {"api_key": {}}
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

   /****************************merchants services****************************/
    /**
     * @OA\Get(
     ** path="/api/admin/get-all-merchants",
     *   tags={"Admin"},
     *   summary="Get all merchants",
     *   operationId="get all merchants",
     *
     *   @OA\Response(
     *      response=200,
     *       description="Success",
     *     ),
     *     security={
     *       {"api_key": {}}
     *     }
     *
     *)
     **/
    public function getAllMerchants()
    {
        return $this->successfulResponse(User::where('role_id',1)->get());
    }

    /****************************user services****************************/
    /**
     * @OA\Get(
     ** path="/api/admin/get-all-users",
     *   tags={"Admin"},
     *   summary="Get all user",
     *   operationId="get all user",
     *
     *   @OA\Response(
     *      response=200,
     *       description="Success",
     *     ),
     *     security={
     *       {"api_key": {}}
     *     }
     *
     *)
     **/
    public function getAllUsers()
    {
        return $this->successfulResponse(User::where('role_id',0)->get());
    }

}
