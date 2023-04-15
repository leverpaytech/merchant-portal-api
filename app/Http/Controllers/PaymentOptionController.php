<?php

namespace App\Http\Controllers;

use App\Models\PaymentOption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\PaymentOptionResource;
use Illuminate\Support\Facades\Validator;


class PaymentOptionController extends BaseController
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    /**
     * @OA\Post(
     ** path="/api/payment-option/create",
     *   tags={"Currencies"},
     *   summary="create payment option",
     *   operationId="create payment option",
     *
     *   @OA\Parameter(
     *      currency_id="currency_id",
     *      in="query",
     *      required=true,
     *      @OA\Schema(
     *          type="string"
     *      )
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
    public function store(Request $request)
    {
        $data = $request->all();

        $validator = Validator::make($data, [
            'currency_id' => 'required',
        ]);

        if ($validator->fails())
            return $this->sendError('All fields cannot be empty',$validator->errors());

        if (PaymentOption::where('currency_id',$request->currency_id)->first())
            return $this->sendError('Currency already exist');

        $paymentOption=$this->currencyModel->createPaymentOption($data);

        return $this->successfulResponse(new PaymentOptionResource($paymentOption), 'new payment option successfully created');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\PaymentOption  $paymentOption
     * @return \Illuminate\Http\Response
     */
    public function show(PaymentOption $paymentOption)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\PaymentOption  $paymentOption
     * @return \Illuminate\Http\Response
     */
    public function edit(PaymentOption $paymentOption)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\PaymentOption  $paymentOption
     * @return \Illuminate\Http\Response
     */
     /**
     * @OA\Put(
     ** path="/api/payment-option/{uuid}/currencies",
     *   tags={"PaymentOption"},
     *   summary="update payment option",
     *   operationId="update payment option",
     *
     *   @OA\Parameter(
     *      id="id",
     *      in="query",
     *      required=true,
     *      @OA\Schema(
     *          type="string"
     *      )
     *   ),
     *   @OA\Parameter(
     *      currency_id="currency_id",
     *      in="query",
     *      required=true,
     *      @OA\Schema(
     *          type="string"
     *      )
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
    public function update(Request $request, $id)
    {
        PaymentOption::where('id', $id)->update(['currency_id', $request->currency_id]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\PaymentOption  $paymentOption
     * @return \Illuminate\Http\Response
     */
    public function destroy(PaymentOption $paymentOption)
    {
        //
    }
}
