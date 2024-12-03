<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\BaseController;
use App\Http\Controllers\Controller;
use App\Models\{Kyc,ActivityLog,User,KycVerification};
use App\Services\{SmsService,ZeptomailService,QoreIdService};
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

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
     *     summary="Get all kyc list",
     *     operationId="Get all kyc list",
     *
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by KYC status: all, approved, pending, declined",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *             enum={"all", "approved", "pending", "declined"},
     *             default="all"
     *         )
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
    */
    public function getKycForApproval(Request $request)
    {
        //return ZeptomailService::sendMailZeptoMail('Testing' ,'Test Message', 'abdilkura@gmail.com');
        // $message=['name'=>'Abdul Kura'];
        // return ZeptomailService::sendTemplateZeptoMail("2d6f.117fe6ec4fda4841.k1.80540ec1-7b4f-11ef-ba81-5254000b1a0e.19229b699aa" ,$message, 'abdilkura@gmail.com');
           
        $status = $request->query('status');
        if($status=='all')
        {
            $kycs = KycVerification::join('users', 'users.id', '=', 'kyc_verifications.user_id')
                ->get(['kyc_verifications.*','users.first_name','users.last_name']);
        }
        else{
            $kycs = KycVerification::join('users', 'users.id', '=', 'kyc_verifications.user_id')
                ->where('kyc_verifications.status', $status)
                ->get(['kyc_verifications.*','users.first_name','users.last_name']);
        }
        
        if (!$kycs) {
            return $this->sendError('Error', 'No Available KYC', 422);
        }
        $kycs = $kycs->map(function ($kyc) {
            // Transforming or adding fields
            return [
                'uuid' => $kyc->uuid,
                'first_name' => $kyc->first_name,
                'last_name' => $kyc->first_name,

                'phone' => $kyc->phone,
                'phone_status' => $kyc->phone_verification_code == 1 ? 'verified' : 'not verified',

                'email' => $kyc->email,
                'email_status' => $kyc->email_verification_code == 1 ? 'verified' : 'not verified',

                'nin' => $kyc->nin,
                'nin_status' => $kyc->nin_details ? 'verified' : 'not verified',
                'nin_details' => $kyc->nin_details,

                'bvn' => $kyc->bvn,
                'bvn_status' => $kyc->bvn_details ? 'verified' : 'not verified',
                'bvn_details' => $kyc->bvn_details,

                'contact_address' => $kyc->contact_address,
                'proof_of_address' => $kyc->proof_of_address ? 'verified' : 'not verified',
                'live_face_verification' => $kyc->live_face_verification ? 'verified' : 'not verified',
                'admin_approval_status' => $kyc->status,
                
            ];
        });

        return $this->successfulResponse($kycs, 'KYC list retrieved successfully', 200);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/brails-kyc/get-user-kyc-details",
     *     tags={"Brails KYC"},
     *     summary="Get user kyc details",
     *     operationId="Get user kyc details",
     *
    *     @OA\Parameter(
   *         name="uuid",
   *         in="query",
   *         required=true,
   *         description="user uuid",
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
    */
    public function getUserKycDetails(Request $request)
    {
        $allUsers = User::where('id', '>', 0)->get(['id', 'phone']);

        foreach ($allUsers as $user) {
            $id = $user->id;
            $phone = $user->phone;

            // Remove spaces and non-numeric characters
            $phone = preg_replace('/\D/', '', $phone);

            // Handle country code +234 or 234
            if (str_starts_with($phone, '+234')) {
                $phone = '0' . substr($phone, 4);
            } elseif (str_starts_with($phone, '234')) {
                $phone = '0' . substr($phone, 3);
            }

            // Ensure phone starts with '0'
            if (!str_starts_with($phone, '0')) {
                $phone = '0' . $phone;
            }

            // Validate phone length (11 digits)
            if (preg_match('/^0\d{10}$/', $phone)) {
                // Update valid phone numbers in the database
                User::where('id', $id)->update(['phone' => $phone]);
            } else {
                // Optionally log invalid numbers for review
                error_log("Invalid phone number for user ID $id: {$user->phone}");
            }
        }


        // Output the transformed phone numbers
        //dd(User::where('id', '>', 0)->get(['phone']));
        // $allUsers->each(function ($phone) {
        //     echo $phone . "<br/>";
        // });


        $user_id = Auth::user()->id;
        $uuid = $request->query('uuid');

        $kycs = KycVerification::join('users', 'users.id', '=', 'kyc_verifications.user_id')
            ->where('users.uuid', $uuid)
            ->get(['kyc_verifications.*','users.first_name','users.last_name']);
        
        if (!$kycs) {
            return $this->sendError('Error', 'No Available KYC', 422);
        }
        $kycs = $kycs->map(function ($kyc) {
            // Transforming or adding fields
            return [
                'uuid' => $kyc->uuid,
                'first_name' => $kyc->first_name,
                'last_name' => $kyc->first_name,

                'phone' => $kyc->phone,
                'phone_status' => $kyc->phone_verification_code == 1 ? 'verified' : 'not verified',

                'email' => $kyc->email,
                'email_status' => $kyc->email_verification_code == 1 ? 'verified' : 'not verified',

                'nin' => $kyc->nin,
                'nin_status' => $kyc->nin_details ? 'verified' : 'not verified',
                'nin_details' => $kyc->nin_details,

                'bvn' => $kyc->bvn,
                'bvn_status' => $kyc->bvn_details ? 'verified' : 'not verified',
                'bvn_details' => $kyc->bvn_details,

                'contact_address' => $kyc->contact_address,
                'proof_of_address' => $kyc->proof_of_address ? 'verified' : 'not verified',
                'live_face_verification' => $kyc->live_face_verification ? 'verified' : 'not verified',
                'admin_approval_status' => $kyc->status,
                
            ];
        });

        return $this->successfulResponse($kycs, 'User KYC Details retrieved successfully', 200);
    }

    /**
    * @OA\Post(
    ** path="/api/v1/brails-kyc/approve-reject-kyc",
    *   tags={"Brails KYC"},
    *   summary="Admin (approve or reject kyc by admin)",
    *   operationId="Admin (approve or reject kyc by admin)",
    *
    *   @OA\RequestBody(
    *      @OA\MediaType(mediaType="multipart/form-data",
    *          @OA\Schema(
    *              required={"status","uuid"},
    *              @OA\Property(property="uuid", type="string", description=""),
    *              @OA\Property(property="status", type="strinf", enum={"approved", "pending", "declined"}, default="approved", description=""),
    *              @OA\Property(property="admin_comment", type="string", description=""),
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
    public function kycApproval(Request $request)
    {
        // Get all input data
        $data = $request->all();

        // Validation rules
        $validator = Validator::make($data, [
            'status' => 'required|string',
            'uuid' => 'required|string',
            'admin_comment' => 'nullable|string',
        ]);

        // If validation fails, return a 422 error with validation messages
        if ($validator->fails()) {
            return $this->sendError('Error', $validator->errors(), 422);
        }

        //
        // Find the user's KYC record
        $kyc = KycVerification::where('uuid', $data['uuid'])->first();

        $kyc->status = $data['status'];
        $kyc->admin_comment = $data['admin_comment'];
        $kyc->save();

        // Return a success response
        return $this->successfulResponse([], "KYC successfully ".ucwords($data['status'])." ", 200);
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
