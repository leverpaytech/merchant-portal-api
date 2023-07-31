<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\BaseController;
use App\Http\Controllers\Controller;
use App\Models\{Kyc,ActivityLog,User};
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class KycController extends BaseController
{
    /**
     * @OA\Post(
     ** path="/api/v1/add-kyc",
     *   tags={"KYC"},
     *   summary="Add KYC document",
     *   operationId="Add KYC document",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"document_name","document_link"},
     *              @OA\Property( property="document_name", type="string"),
     *              @OA\Property( property="document_link", type="file"),
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
    public function addKyc(Request $request)
    {
        $data = $request->all();

        $validator = Validator::make($data, [
            'document_name' => 'required',
            'document_link' => 'required|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        if ($validator->fails())
        {
            return $this->sendError('Error',$validator->errors(),422);
        }

        $user_id=Auth::user()->id;
        //$user_id=2;
        $data['user_id']=$user_id;

        $uploadUrl = cloudinary()->upload($request->file('document_link')->getRealPath(),
            ['folder'=>'leverpay/kyc']
        )->getSecurePath();
        $data['document_link']=$uploadUrl;

        Kyc::create($data);
        User::where('id', $user_id)->update(['kyc_status'=>1]);

        $data2['activity']="Add KYC";
        $data2['user_id']=$user_id;

        ActivityLog::createActivity($data2);

        $response = [
            'success' => true,
            'document_name' => $data['document_name'],
            'document_link' => $uploadUrl,
            'message' => "KYC successfully saved"
        ];

        return response()->json($response, 200);
    }

    /**
     * @OA\Get(
     ** path="/api/v1/kyc-details",
     *   tags={"KYC"},
     *   summary="Get all kyc details",
     *   operationId="Get all kyc details",
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
    public function getKycDocument()
    {
        $user_id=Auth::user()->id;
        $kycs=Kyc::where('user_id', $user_id)->get();

        return $this->successfulResponse($kycs, 'kyc details successfully retrieved');

    }
}
