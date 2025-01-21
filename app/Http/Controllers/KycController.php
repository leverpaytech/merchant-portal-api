<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\BaseController;
use App\Http\Controllers\Controller;
use App\Models\{Kyc,ActivityLog,User,KycVerification,Wallet};
use App\Services\{SmsService,ZeptomailService,QoreIdService};
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Support\Facades\DB;

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
    *              @OA\Property( property="phone", type="string", description="Valid phone number (e.g 08136908764) "),
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

        $checkBalance = $this->checkWalletBalance($user_id);
        if (!$checkBalance) {
            return $this->sendError('Error', 'Kindly fund your wallet with aleast N500 to proceed wth you kyc', 422);
        }

        $validator = Validator::make($request->all(), [
            'phone' => [
                'required',
                'regex:/^\d{11}$/', // Ensure the phone number  is exactly 11 digits
                // 'regex:/^\d{3}\d{10}$/', // Ensure the phone number (country code + phone) is exactly 11 digits
                Rule::unique('users', 'phone')->ignore($user_id), // Unique check in 'users' table, ignoring the current user
            ],
        ], [
            'phone.regex' => 'The phone number must include a valid country code and must be 11 digits. (eg. 09033262626)',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error', $validator->errors(), 422);
        }

        $actPhn="234".substr($request->phone,1,strlen($request->phone));

        $user=User::where('id',$user_id)->where('phone', $request->phone)->first();
        if(!$user)
        {
            return $this->sendError('Error', 'The phone number provided does not exist', 422);
        }

        $phoneNumber = $actPhn;
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

        $checkBalance = $this->checkWalletBalance($user_id);
        if (!$checkBalance) {
            return $this->sendError('Error', 'Kindly fund your wallet with aleast N500 to proceed wth you kyc', 422);
        }

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
        $message=[
            'name'=>$user->first_name,
            'otp'=>$code
        ];
        //send mail
        $response=ZeptomailService::sendTemplateZeptoMail("2d6f.117fe6ec4fda4841.k1.a105eb80-7b4e-11ef-ba81-5254000b1a0e.19229b0e238" ,$message, $request->email);

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
        
        $kyc = KycVerification::where('user_id', $user_id)->first();
        
        if($kyc->$typeCode==$data['otp'])
        {
            $kyc->$column = now();
            $kyc->$typeCode = 1; // Assuming 1 means verified
            $kyc->save();

            // Log the activity
            $data['activity'] = "Verifying " . ucfirst($verificationType) . " using OTP sent";
            $data['user_id'] = $user_id;
            ActivityLog::createActivity($data);
            $getUser=User::where('id', $user_id)->first();
            $message=[
                'name'=>$getUser->first_name
            ];
            if($verificationType=='phone')
            {
                //send mail
                ZeptomailService::sendTemplateZeptoMail("2d6f.117fe6ec4fda4841.k1.5a0f2920-7b5e-11ef-ba81-5254000b1a0e.1922a17ecb2" ,$message, $getUser->email);
            }
            else{
                //send mail
                ZeptomailService::sendTemplateZeptoMail("2d6f.117fe6ec4fda4841.k1.80540ec1-7b4f-11ef-ba81-5254000b1a0e.19229b699aa" ,$message, $getUser->email);
            }
            

            return $this->successfulResponse([], ucfirst($verificationType) . ' successfully verified', 200);
        }
        elseif($kyc->$typeCode==1)
        {
            return $this->successfulResponse([], ucfirst($verificationType) . ' already verified', 200);
        } else {
            return $this->sendError('Verification Error', 'OTP verification failed. Please check the OTP or the verification method.', 422);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/brails-kyc/check-kyc-status",
     *     tags={"Brails KYC"},
     *     summary="Check KYC Status",
     *     operationId="Check KYC Status",
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
    public function getKYCStatus()
    {
        $user_id = Auth::user()->id;

        $kyc = KycVerification::where('user_id', $user_id)->first();

        if (!$kyc) {
            return $this->sendError('Error', 'KYC record not found for this user', 422);
        }

        $kyc_status = [
            'phone' => $kyc->phone_verification_code == 1 ? 'verified' : 'not verified',
            'email' => $kyc->email_verification_code == 1 ? 'verified' : 'not verified',
            'nin' => $kyc->nin_details ? 'verified' : 'not verified',
            'bvn' => $kyc->bvn_details ? 'verified' : 'not verified',
            'proof_of_address' => $kyc->proof_of_address ? 'verified' : 'not verified',
            'live_face_verification' => $kyc->live_face_verification ? 'verified' : 'not verified',
            'admin_approval_status' => $kyc->status,
        ];

        return $this->successfulResponse($kyc_status, 'KYC status retrieved successfully', 200);
    }

    /**
    * @OA\Post(
    ** path="/api/v1/brails-kyc/verify-nin",
    *   tags={"Brails KYC"},
    *   summary="NIN Verification",
    *   operationId="NIN Verification",
    *
    *    @OA\RequestBody(
    *      @OA\MediaType( mediaType="multipart/form-data",
    *          @OA\Schema(
    *              required={"nin"},
    *              @OA\Property( property="nin", type="string", description="Enter 11 digits valid NIN Number"),
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
    *      description="Not found"
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
    public function ninVerification(Request $request)
    {
        // Ensure the user is authenticated
        if (!Auth::check()) {
            return $this->sendError('Unauthorized Access', [], 401);
        }

        $user = Auth::user();

        $checkBalance = $this->checkWalletBalance($user->id);
        if (!$checkBalance) {
            return $this->sendError('Error', 'Kindly fund your wallet with aleast N500 to proceed wth you kyc', 422);
        }

        // Validate the NIN input
        $validator = Validator::make($request->all(), [
            'nin' => [
                'required',
                'numeric',
                'regex:/^\d{11}$/', // Ensure NIN is exactly 11 digits
            ],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }

        $checkIfNotUse = KycVerification::where('nin', $request->nin)->where('user_id', '!=', $user->id)->first();

        if ($checkIfNotUse) {
            return $this->sendError('Error', 'NIN already used by someone', 422);
        }

        // Generate access token using QoreIdService
        $accessToken = QoreIdService::generateAccessToken();

        // Call the verifyNIN method with the required parameters
        $ninVerificationResult = QoreIdService::verifyNIN(
            $request->nin,
            $user->first_name,
            $user->last_name,
            $accessToken
        );
        //return response()->json($ninVerificationResult['nin']);

        if(isset($ninVerificationResult['error']))
        {
            return $this->sendError('Error', $ninVerificationResult['error'], 422); 
        }
        
        $kyc = KycVerification::updateOrCreate(
            ['user_id' => $user->id ],
            [
                'nin' => $request->nin,
                'nin_details' => $ninVerificationResult['nin']
            ]
        );

        $data['activity']="NIN  Verification (".$request->nin.") for brails KYC verification";
        $data['user_id']=$user->id;
        ActivityLog::createActivity($data);
        // Return the result from the API call
        //return response()->json($ninVerificationResult['nin']);
        return $this->successfulResponse([], 'NIN successfully submitted', 200);
    }

    /**
    * @OA\Post(
    ** path="/api/v1/brails-kyc/verify-bvn",
    *   tags={"Brails KYC"},
    *   summary="BVN Verification",
    *   operationId="BVN Verification",
    *
    *    @OA\RequestBody(
    *      @OA\MediaType( mediaType="multipart/form-data",
    *          @OA\Schema(
    *              required={"bvn"},
    *              @OA\Property( property="bvn", type="string", description="Enter 11 digits valid BVN Number"),
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
    *      description="Not found"
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

    public function bvnVerification(Request $request)
    {
        // Ensure the user is authenticated
        if (!Auth::check()) {
            return $this->sendError('Unauthorized Access', [], 401);
        }

        $user = Auth::user();

        $checkBalance = $this->checkWalletBalance($user->id);
        if (!$checkBalance) {
            return $this->sendError('Error', 'Kindly fund your wallet with aleast N500 to proceed wth you kyc', 422);
        }

        // Validate the BVN input
        $validator = Validator::make($request->all(), [
            'bvn' => [
                'required',
                'numeric',
                'regex:/^\d{11}$/', // Ensure NIN is exactly 11 digits
            ],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }

        $checkIfNotUse = KycVerification::where('bvn', $request->bvn)->where('user_id', '!=', $user->id)->first();

        if ($checkIfNotUse) {
            return $this->sendError('Error', 'BVN already used by someone', 422);
        }
        // Generate access token using QoreIdService
        $accessToken = QoreIdService::generateAccessToken();

        // Call the verifyBVN method with the required parameters
        $bvnVerificationResult = QoreIdService::verifyBVN(
            $request->bvn,
            $user->first_name,
            $user->last_name,
            $accessToken
        );
        //return response()->json($bvnVerificationResult['error']);
        if(isset($bvnVerificationResult['error']))
        {
            return $this->sendError('Error', $bvnVerificationResult['error'], 422); 
        }
        
        $kyc = KycVerification::updateOrCreate(
            ['user_id' => $user->id ],
            [
                'bvn' => $request->bvn,
                'bvn_details' => $bvnVerificationResult['bvn_match']['fieldMatches']
            ]
        );

        $data['activity']="BVN  Verification (".$request->bvn.") for brails KYC verification";
        $data['user_id']=$user->id;
        ActivityLog::createActivity($data);
        return $this->successfulResponse([], 'BVN successfully submitted', 200);

        
    }
    
    /**
    * @OA\Post(
    ** path="/api/v1/brails-kyc/verify-address",
    *   tags={"Brails KYC"},
    *   summary="Contact Address Verification",
    *   operationId="Contact Address Verification",
    *
    *   @OA\RequestBody(
    *      @OA\MediaType(mediaType="multipart/form-data",
    *          @OA\Schema(
    *              required={"address","proof_of_address"},
    *              @OA\Property(property="address", type="string", description="Contact Address"),
    *              @OA\Property(property="proof_of_address", type="file", description="Upload proof of address (format jpg, png, or pdf)"),
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
    *      description="Not found"
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
    public function verifyAddress(Request $request)
    {
        // Get all input data
        $data = $request->all();
        
        // Validation rules
        $validator = Validator::make($data, [
            'proof_of_address' => 'required|mimes:jpeg,png,jpg,pdf|max:4096', // Restrict file type and size
            'address' => 'required|string', // Address must be provided
        ]);

        // If validation fails, return a 422 error with validation messages
        if ($validator->fails()) {
            return $this->sendError('Error', $validator->errors(), 422);
        }

        // Retrieve the authenticated user's ID
        $user_id = Auth::user()->id;

        $checkBalance = $this->checkWalletBalance($user_id);
        if (!$checkBalance) {
            return $this->sendError('Error', 'Kindly fund your wallet with aleast N500 to proceed wth you kyc', 422);
        }

        // Find the user's KYC record
        $kyc = KycVerification::where('user_id', $user_id)->first();

        // Upload the proof of address to Cloudinary
        $pAddressUrl = Cloudinary::upload($request->file('proof_of_address')->getRealPath(), [
            'folder' => 'leverpay/kyc'
        ])->getSecurePath();

        // Update the KYC record with the new address and proof of address URL
        $kyc->contact_address = $data['address'];
        $kyc->proof_of_address = $pAddressUrl;
        $kyc->save();

        // Return a success response
        return $this->successfulResponse([], 'Address successfully submitted', 200);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/brails-kyc/get-kyc-list",
     *     tags={"Brails KYC"},
     *     summary="Retrieve the list of KYCs",
     *     operationId="getKycList",
     *     description="Fetches a list of KYCs filtered by their status. If no filter is applied, all KYCs are retrieved.",
     *
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=true,
     *         description="Filter by KYC status. Valid options are 'all', 'approved', 'pending', or 'declined'.",
     *         @OA\Schema(
     *             type="string",
     *             enum={"all", "approved", "pending", "declined"},
     *             default="all"
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successfully retrieved the KYC list.",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="kyc_uuid", type="string", description="Kyc's UUID"),
     *                 @OA\Property(property="user_uuid", type="string", description="User's UUID"),
     *                 @OA\Property(property="first_name", type="string", description="User's first name."),
     *                 @OA\Property(property="last_name", type="string", description="User's last name."),
     *                 @OA\Property(property="phone", type="string", description="User's phone number."),
     *                 @OA\Property(property="phone_status", type="string", description="Phone verification status."),
     *                 @OA\Property(property="email", type="string", description="User's email address."),
     *                 @OA\Property(property="email_status", type="string", description="Email verification status."),
     *                 @OA\Property(property="nin", type="string", description="User's National Identification Number."),
     *                 @OA\Property(property="nin_status", type="string", description="NIN verification status."),
     *                 @OA\Property(property="nin_details", type="object", description="Details from the NIN verification."),
     *                 @OA\Property(property="bvn", type="string", description="User's Bank Verification Number."),
     *                 @OA\Property(property="bvn_status", type="string", description="BVN verification status."),
     *                 @OA\Property(property="bvn_details", type="object", description="Details from the BVN verification."),
     *                 @OA\Property(property="contact_address", type="string", description="User's contact address."),
     *                 @OA\Property(property="proof_of_address", type="string", description="Proof of address document."),
     *                 @OA\Property(property="live_face_verification", type="string", description="Live face verification result."),
     *                 @OA\Property(property="admin_approval_status", type="string", description="Admin's approval status.")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="No KYCs found or an error occurred."
     *     ),
     *
     *     security={
     *         {"bearer_token": {}}
     *     }
     * )
    */

    public function getKycForApproval(Request $request)
    {
        $status = $request->query('status', 'all'); // Default to 'all' if no status is provided.

        $kycsQuery = KycVerification::join('users', 'users.id', '=', 'kyc_verifications.user_id')
            ->select(['kyc_verifications.*', 'users.uuid as user_uuid','users.first_name', 'users.last_name']);

        if ($status !== 'all') {
            $kycsQuery->where('kyc_verifications.status', $status);
        }

        $kycs = $kycsQuery->get();

        if ($kycs->isEmpty()) {
            return $this->sendError('No KYCs found', 'No KYCs match the given criteria.', 422);
        }

        $kycs = $kycs->map(function ($kyc) {
            $ninDetails = $kyc->nin_details ? json_decode($kyc->nin_details, true) : null;

            return [
                'kyc_uuid' => $kyc->uuid,
                'user_uuid' => $kyc->user_uuid,
                'first_name' => $kyc->first_name,
                'last_name' => $kyc->last_name,
                'phone' => $kyc->phone,
                'phone_status' => $kyc->phone_verification_code == 1 ? 'verified' : 'not verified',
                'email' => $kyc->email,
                'email_status' => $kyc->email_verification_code == 1 ? 'verified' : 'not verified',
                'nin' => $kyc->nin,
                'nin_status' => $kyc->nin_details ? 'submitted' : 'not submitted',
                'nin_details' => $ninDetails,
                'bvn' => $kyc->bvn,
                'bvn_status' => $kyc->bvn_details ? 'submitted' : 'not submitted',
                'bvn_details' => $kyc->bvn_details,
                'contact_address' => $kyc->contact_address,
                'proof_of_address' => $kyc->proof_of_address,
                'live_face_verification' => $kyc->live_face_verification,
                'admin_approval_status' => $kyc->status,
            ];
        });

        return $this->successfulResponse($kycs, 'KYC list retrieved successfully.', 200);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/brails-kyc/get-user-kyc-details",
     *     tags={"Brails KYC"},
     *     summary="Retrieve User KYC Details",
     *     description="Fetch detailed Know Your Customer (KYC) information for a user by providing their UUID.",
     *     operationId="getUserKycDetails",
     * 
     *     @OA\Parameter(
     *         name="uuid",
     *         in="query",
     *         required=true,
     *         description="Unique identifier (UUID) of the user.",
     *         @OA\Schema(
     *             type="string",
     *             example="123e4567-e89b-12d3-a456-426614174000"
     *         )
     *     ),
     * 
     *     @OA\Response(
     *         response=200,
     *         description="KYC details retrieved successfully",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="kyc_uuid", type="string", description="Kyc's UUID"),
     *                 @OA\Property(property="user_uuid", type="string", description="User's UUID"),
     *                 @OA\Property(property="first_name", type="string", description="User's first name"),
     *                 @OA\Property(property="last_name", type="string", description="User's last name"),
     *                 @OA\Property(property="phone", type="string", description="User's phone number"),
     *                 @OA\Property(property="phone_status", type="string", description="Phone verification status"),
     *                 @OA\Property(property="email", type="string", description="User's email address"),
     *                 @OA\Property(property="email_status", type="string", description="Email verification status"),
     *                 @OA\Property(property="nin", type="string", description="National Identification Number"),
     *                 @OA\Property(property="nin_status", type="string", description="NIN submission status"),
     *                 @OA\Property(property="nin_details", type="object", description="Decoded NIN details"),
     *                 @OA\Property(property="bvn", type="string", description="Bank Verification Number"),
     *                 @OA\Property(property="bvn_status", type="string", description="BVN submission status"),
     *                 @OA\Property(property="bvn_details", type="string", description="BVN details"),
     *                 @OA\Property(property="contact_address", type="string", description="User's contact address"),
     *                 @OA\Property(property="proof_of_address", type="string", description="Proof of address document"),
     *                 @OA\Property(property="live_face_verification", type="string", description="Live face verification status"),
     *                 @OA\Property(property="admin_approval_status", type="string", description="Admin approval status for KYC")
     *             )
     *         )
     *     ),
     * 
     *     @OA\Response(
     *         response=422,
     *         description="No available KYC details for the specified UUID",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="No Available KYC")
     *         )
     *     ),
     * 
     *     security={
     *         {"bearer_token": {}}
     *     }
     * )
    */

    public function getUserKycDetails(Request $request)
    {
        $user_id = Auth::user()->id;
        $uuid = $request->query('uuid');

        // Fetch user KYC details by joining with the 'users' table
        $kycs = KycVerification::join('users', 'users.id', '=', 'kyc_verifications.user_id')
            ->where('users.uuid', $uuid)
            ->get(['kyc_verifications.*', 'users.uuid as user_uuid','users.first_name', 'users.last_name']);

        // Return error response if no KYC details found
        if ($kycs->isEmpty()) {
            return $this->sendError('Error', 'No Available KYC', 422);
        }

        // Transform KYC details
        $kycs = $kycs->map(function ($kyc) {
            $ninDetails = $kyc->nin_details ? json_decode($kyc->nin_details, true) : null;

            return [
                'kyc_uuid' => $kyc->uuid,
                'user_uuid' => $kyc->user_uuid,
                'first_name' => $kyc->first_name,
                'last_name' => $kyc->last_name,
                'phone' => $kyc->phone,
                'phone_status' => $kyc->phone_verification_code == 1 ? 'verified' : 'not verified',
                'email' => $kyc->email,
                'email_status' => $kyc->email_verification_code == 1 ? 'verified' : 'not verified',
                'nin' => $kyc->nin,
                'nin_status' => $kyc->nin_details ? 'submitted' : 'not submitted',
                'nin_details' => $ninDetails,
                'bvn' => $kyc->bvn,
                'bvn_status' => $kyc->bvn_details ? 'submitted' : 'not submitted',
                'bvn_details' => $kyc->bvn_details,
                'contact_address' => $kyc->contact_address,
                'proof_of_address' => $kyc->proof_of_address,
                'live_face_verification' => $kyc->live_face_verification,
                'admin_approval_status' => $kyc->status,
            ];
        });

        // Return success response
        return $this->successfulResponse($kycs, 'User KYC Details retrieved successfully', 200);
    }

    /**
     * @OA\Post(
     *   path="/api/v1/brails-kyc/approve-reject-kyc",
     *   tags={"Brails KYC"},
     *   summary="Approve or reject KYC by admin",
     *   operationId="ApproveOrRejectKYC",
     *
     *   @OA\RequestBody(
     *      @OA\MediaType(
     *          mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"status", "uuid"},
     *              @OA\Property(property="uuid", type="string", description="Unique identifier for the KYC record"),
     *              @OA\Property(property="status", type="string", enum={"approved", "pending", "declined"}, default="approved", description="KYC approval status"),
     *              @OA\Property(property="admin_comment", type="string", description="Optional admin comment"),
     *          ),
     *      ),
     *   ),
     *
     *   @OA\Response(
     *      response=200,
     *      description="Success",
     *      @OA\MediaType(
     *          mediaType="application/json",
     *      )
     *   ),
     *   @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     *   @OA\Response(
     *      response=401,
     *      description="Unauthenticated"
     *   ),
     *   @OA\Response(
     *      response=404,
     *      description="KYC record not found"
     *   ),
     *   @OA\Response(
     *      response=500,
     *      description="Internal Server Error"
     *   ),
     *   security={
     *      {"bearer_token": {}}
     *   }
     * )
    */

    public function kycApproval(Request $request)
    {
        try {
            // Validate incoming request data
            $validator = Validator::make($request->all(), [
                'status' => 'required|string|in:approved,pending,declined',
                'uuid' => 'required|string',
                'admin_comment' => 'nullable|string',
            ]);

            // If validation fails, return a 422 error with validation messages
            if ($validator->fails()) {
                return $this->sendError('Validation Error', $validator->errors(), 422);
            }

            // Extract validated data
            $data = $validator->validated();

            // Find the KYC record by UUID
            $kyc = KycVerification::where('uuid', $data['uuid'])->first();

            if (!$kyc) {
                return $this->sendError('KYC record not found', null, 404);
            }

            // Update KYC status and admin comment
            $kyc->status = $data['status'];
            $kyc->admin_comment = $data['admin_comment'] ?? null;
            if($kyc->status=='declined')
            {
                $kyc->phone=NULL;
                $kyc->emaill=NULL;
                $kyc->nin=NULL;
                $kyc->bvn=NULL;
                $kyc->nin_details=NULL;
                $kyc->bvn_details=NULL;
                $kyc->contact_address=NULL;
                $kyc->proof_of_address=NULL;
                $kyc->live_face_verification=NULL;
                $kyc->phone_verified_at=NULL;
                $kyc->email_verified_at=NULL;
                $kyc->nin_verified_at=NULL;
                $kyc->bvn_verified_at=NULL;
                $kyc->address_verified_at=NULL;
                $kyc->live_face_verified_at=NULL;
            }
            $kyc->save();


            // Return success response
            return $this->successfulResponse(
                [],
                "KYC successfully " . ucfirst($data['status']),
                200
            );
        } catch (\Exception $e) {
            // Handle unexpected errors
            return $this->sendError('An unexpected error occurred', $e->getMessage(), 500);
        }
    }
    
    protected function checkWalletBalance($userId)
    {
        $checkBalance = Wallet::where('user_id', $userId)->first(['withdrawable_amount', 'amount']);

        return $checkBalance && $checkBalance->withdrawable_amount >= 500;
    }

    /**
     * @OA\Get(
     *     path="/api/v1/brails-kyc/reset-user-kyc",
     *     tags={"Brails KYC"},
     *     summary="Reset User kyc",
     *     description="Reset user kyc",
     *     operationId="resettUserKyc",
     * 
     *     @OA\Parameter(
     *         name="uuid",
     *         in="query",
     *         required=true,
     *         description="Unique identifier (UUID) of the user.",
     *         @OA\Schema(
     *             type="string",
     *             example="123e4567-e89b-12d3-a456-426614174000"
     *         )
     *     ),
     *  
     *     @OA\Response(
     *         response=422,
     *         description="No available KYC details for the specified UUID",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="No Available KYC")
     *         )
     *     ),
     * 
     *     security={
     *         {"bearer_token": {}}
     *     }
     * )
    */

    public function resetKyc(Request $request)
    {
        $userId = Auth::id(); // Use Auth::id() for simplicity
        $uuid = $request->query('uuid');

        try {
            // Fetch KYC record
            $kyc = KycVerification::join('users', 'users.id', '=', 'kyc_verifications.user_id')
                ->where('users.uuid', $uuid)
                ->select('kyc_verifications.*')
                ->firstOrFail();

            // Reset KYC details in a transaction
            DB::transaction(function () use ($kyc) {
                $kyc->update([
                    'phone' => null,
                    'email' => null,
                    'nin' => null,
                    'bvn' => null,
                    'nin_details' => null,
                    'bvn_details' => null,
                    'contact_address' => null,
                    'proof_of_address' => null,
                    'live_face_verification' => null,
                    'phone_verified_at' => null,
                    'email_verified_at' => null,
                    'nin_verified_at' => null,
                    'bvn_verified_at' => null,
                    'address_verified_at' => null,
                    'live_face_verified_at' => null,
                ]);
            });

            // Return success response
            return $this->successfulResponse([], 'User KYC successfully reset', 200);
        } catch (ModelNotFoundException $e) {
            // Handle case where UUID does not match
            return $this->sendError('Error', 'KYC record not found', 404);
        } catch (\Exception $e) {
            // Handle unexpected exceptions
            return $this->sendError('Error', 'An unexpected error occurred ', 500);
        }
    }

    
}
