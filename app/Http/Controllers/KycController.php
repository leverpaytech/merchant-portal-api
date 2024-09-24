<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\BaseController;
use App\Http\Controllers\Controller;
use App\Models\{Kyc,ActivityLog,User,KycVerification};
use App\Services\{SmsService,ZeptomailService};
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class KycController extends BaseController
{
    /**
    * @OA\Post(
    ** path="/api/v1/brails-kyc/send-phone-verification-otp",
    *   tags={"Brails KYC"},
    *   summary="Send Phone OTP",
    *   operationId="Send Phone OTP",
    *
    *    @OA\RequestBody(
    *      @OA\MediaType( mediaType="multipart/form-data",
    *          @OA\Schema(
    *              required={"phone"},
    *              @OA\Property( property="phone", type="string", description="Valid phone number with country code. (e.g 2348136908764) "),
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
    public function phoneNumberVerification(Request $request)
    {
        $user_id = Auth::user()->id;

        $validator = Validator::make($request->all(), [
            'phone' => [
                'required',
                'regex:/^\d{3}\d{10}$/', // Ensure the phone number (country code + phone) is exactly 11 digits
                Rule::unique('users', 'phone')->ignore($user_id), // Unique check in 'users' table, ignoring the current user
            ],
        ], [
            'phone.regex' => 'The phone number must include a valid country code and must be 11 digits. (eg. 2349033262626)',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error', $validator->errors(), 422);
        }

        $actPhn="0".substr($request->phone,3,strlen($request->phone));

        $user=User::where('id',$user_id)->where('phone', $actPhn)->first();
        if(!$user)
        {
            return $this->sendError('Error', 'The phone number provided does not exist', 422);
        }

        $phoneNumber = $request->phone;
        $code=rand(100000,999999);

        $message="{$code} is your OTP. For enquiry: visit www.leverpay.io";
        
        $response=SmsService::sendSms($message,$phoneNumber);

        if($response)
        {
            $kyc = KycVerification::updateOrCreate(
                ['user_id' => $user_id ],
                [
                    'phone' => $request->phone,
                    'phone_verification_code' => $code
                ]
            );
    
            $data['activity']="Send OTP to ".$request->phone." for brails KYC verification";
            $data['user_id']=$user_id;
            ActivityLog::createActivity($data);
    
            return $this->successfulResponse([], 'Verification OTP sucessfully sent', 200);
        }
        
        return $this->sendError('Error', 'Failed to send Verification OTP', 422);
    }
    
    /**
    * @OA\Post(
    ** path="/api/v1/brails-kyc/send-email-verification-otp",
    *   tags={"Brails KYC"},
    *   summary="Send Email OTP",
    *   operationId="Send Email OTP",
    *
    *    @OA\RequestBody(
    *      @OA\MediaType( mediaType="multipart/form-data",
    *          @OA\Schema(
    *              required={"email"},
    *              @OA\Property( property="email", type="string", description="Valid email address "),
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
    public function emailNumberVerification(Request $request)
    {
        $user_id = Auth::user()->id;

        $validator = Validator::make($request->all(), [
            'phone' => [
                'required|email',
                Rule::unique('users', 'email')->ignore($user_id), // Unique check in 'users' table, ignoring the current user
            ],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error', $validator->errors(), 422);
        }

        $user=User::where('id',$user_id)->where('email', $request->email)->first();
        if(!$user)
        {
            return $this->sendError('Error', 'The email address provided does not exist', 422);
        }

        $code=rand(100000,999999);
        $message="<h3>Hi ".$user->first_name."!</h3><p>{$code} is your OTP. <br/>For enquiry: contact@leverpay.io or visit www.leverpay.io</p>";
        $subject="Email Verification";
        $response=ZeptomailService::sendMailZeptoMail($subject ,$message, $request->email);

        if($response)
        {
            $kyc = KycVerification::updateOrCreate(
                ['user_id' => $user_id ],
                [
                    'email' => $request->email,
                    'email_verification_code' => $code
                ]
            );
    
            $data['activity']="Send OTP to ".$request->email." for brails KYC verification";
            $data['user_id']=$user_id;
            ActivityLog::createActivity($data);
    
            //return response()->json('Verification OTP sucessfully sent ', 200);
            return $this->successfulResponse([], 'Verification OTP sucessfully sent', 200);
        }
        
        return $this->sendError('Error', 'Failed to send Verification OTP', 422);
    }
    
    /**
    * @OA\Post(
    ** path="/api/v1/brails-kyc/verify-otp",
    *   tags={"Brails KYC"},
    *   summary="Verify OTP",
    *   operationId="Verify OTP",
    *
    *    @OA\RequestBody(
    *      @OA\MediaType( mediaType="multipart/form-data",
    *          @OA\Schema(
    *              required={"type","otp"},
    *              @OA\Property( property="type", type="string", description="it should be either phone or email"),
    *              @OA\Property( property="otp", type="string", description="6 digits"),
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
    public function verifyOTP(Request $request)
    {
        $data = $request->all();

        // Validate input data
        $validator = Validator::make($data, [
            'type' => 'required|string|in:email,phone',
            'otp' => 'required|digits:6',
        ]);

        // Handle validation errors
        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }

        $user_id = Auth::user()->id;
        $verificationType = strtolower($data['type']);
        $column = $verificationType === 'phone' ? 'phone_verified_at' : 'email_verified_at';
        $typeCode = $verificationType === 'phone' ? 'phone_verification_code' : 'email_verification_code';
        
        $kyc = KycVerification::where('id', $user_id)
            ->where($typeCode, $data['otp'])
            ->first();

        if ($kyc) {
            $kyc->$column = now();
            $kyc->$typeCode = 1; // Assuming 1 means verified
            $kyc->save();

            // Log the activity
            $data['activity'] = "Verifying " . ucfirst($verificationType) . " using OTP sent";
            $data['user_id'] = $user_id;
            ActivityLog::createActivity($data);

            return $this->successfulResponse([], ucfirst($verificationType) . ' successfully verified', 200);
        } else {
            return $this->sendError('Verification Error', 'OTP verification failed. Please check the OTP or the verification method.', 422);
        }
    }

    // public function addKyc(Request $request)
    // {
    //     $data = $request->all();

    //     $validator = Validator::make($data, [
    //         'document_name' => 'required',
    //         'document_link' => 'required|mimes:jpeg,png,jpg|max:2048'
    //     ]);

    //     if ($validator->fails())
    //     {
    //         return $this->sendError('Error',$validator->errors(),422);
    //     }

    //     $user_id=Auth::user()->id;
    //     //$user_id=2;
    //     $data['user_id']=$user_id;

    //     $uploadUrl = cloudinary()->upload($request->file('document_link')->getRealPath(),
    //         ['folder'=>'leverpay/kyc']
    //     )->getSecurePath();
    //     $data['document_link']=$uploadUrl;

    //     Kyc::create($data);
    //     User::where('id', $user_id)->update(['kyc_status'=>1]);

    //     $data2['activity']="Add KYC";
    //     $data2['user_id']=$user_id;

    //     ActivityLog::createActivity($data2);

    //     $response = [
    //         'success' => true,
    //         'document_name' => $data['document_name'],
    //         'document_link' => $uploadUrl,
    //         'message' => "KYC successfully saved"
    //     ];

    //     return response()->json($response, 200);
    // }

    
    // public function getKycDocument()
    // {
    //     $user_id=Auth::user()->id;
    //     $kycs=Kyc::where('user_id', $user_id)->get();

    //     return $this->successfulResponse($kycs, 'kyc details successfully retrieved');

    // }
}
