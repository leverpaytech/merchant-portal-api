<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Currency;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\CurrencyResource;
use Illuminate\Support\Facades\Validator;

class CurrencyController extends BaseController
{
    protected $currencyModel;

    public function __construct(Currency $currency)
    {
        $this->currencyModel = $currency;
    }

    /**
     * @OA\Post(
     ** path="/api/admin/add-new-currency",
     *   tags={"Admin"},
     *   summary="create multiple currency",
     *   operationId="create multiple currency",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"name","currency_code"},
     *              @OA\Property( property="name", type="string"),
     *              @OA\Property( property="currency_code", type="string")
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
    public function create(Request $request)
    {
        $data = $request->all();

        $validator = Validator::make($data, [
            'name' => 'required',
            'currency_code' => 'unique:currencies',
        ]);

        if ($validator->fails())
            return $this->sendError('All fields cannot be empty',$validator->errors());

        if (Currency::where('currency_code',$request->currency_code)->first())
            return $this->sendError('Invalid Code');

        $currency=$this->currencyModel->createCurrency($data);

        return $this->successfulResponse(new CurrencyResource($currency), 'new currency successfully created');
    }
    

}
