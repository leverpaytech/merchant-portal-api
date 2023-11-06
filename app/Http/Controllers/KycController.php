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
    
    public function addKyc(Request $request)
    {
        $data = $request->all();

        $validator = Validator::make($data, [
            'document_name' => 'required',
            'document_link' => 'required|mimes:jpeg,png,jpg|max:2048'
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

    
    public function getKycDocument()
    {
        $user_id=Auth::user()->id;
        $kycs=Kyc::where('user_id', $user_id)->get();

        return $this->successfulResponse($kycs, 'kyc details successfully retrieved');

    }
}
