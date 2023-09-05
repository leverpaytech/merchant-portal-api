<?php

namespace App\Http\Controllers\Merchant;
use App\Http\Controllers\BaseController;
use App\Http\Controllers\Controller;
use App\Mail\SendEmailVerificationCode;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Models\ActivityLog;
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
     *              required={"product_name","price"},
     *              @OA\Property( property="product_name", type="string"),
     *              @OA\Property( property="price", type="string"),
     *              @OA\Property( property="product_description", type="string"),
     *              @OA\Property( property="product_image", type="file")
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
    public function createInvoice(Request $request)
    {
        $data = $request->all();

        $validator = Validator::make($data, [
            'product_name'=>'required|string',
            'product_description'=>'nullable|string',
            'price'=>'required|numeric',
            'product_image' => 'nullable|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        if ($validator->fails())
        {
            return $this->sendError('Error',$validator->errors(),422);
        }
        
        
        if(!empty($data['product_image']))
        {
            try
            {
                $pImage = cloudinary()->upload($request->file('product_image')->getRealPath(),
                    ['folder'=>'leverpay/invoice']
                )->getSecurePath();

                $data['product_image']= $pImage;

            } catch (\Exception $ex) {
                return $this->sendError($ex->getMessage());
            }
        }

        $data['uuid'] = Uuid::generate()->string;
        $userId=Auth::user()->id;
        $data['user_id']=$userId;


        DB::transaction( function() use($data, $userId) {

            Invoice::create($data);

            $data2['activity']="Create Invoice,  ".$data['uuid'];
            $data2['user_id']=$userId;
            ActivityLog::createActivity($data2);

        });
        
        $invoice = Invoice::where('uuid', $data['uuid'])->get()->first();

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
        return $this->successfulResponse(Invoice::where('uuid',$uuid)->get()->first(), 'Invoice successfully retrieved');
    }
}
