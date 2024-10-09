<?php
namespace App\Http\Controllers;

use App\Http\Controllers\BaseController;
use App\Models\{Kyc, ActivityLog, User, KycVerification};
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use App\Events\SelfieCaptured;
use Illuminate\Support\Facades\Storage;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class SelfieController extends BaseController
{
    /**
     * @OA\Post(
     ** path="/api/v1/brails-kyc/selfie/start",
     *   tags={"Brails KYC"},
     *   summary="Start Selfie",
     *   operationId="Start Selfie",
     *
     *   @OA\Response(
     *      response=200,
     *      description="Success",
     *      @OA\MediaType(mediaType="application/json"),
     *   ),
     *   @OA\Response(
     *      response=401,
     *      description="Unauthenticated"
     *   ),
     *   @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     *   @OA\Response(
     *      response=404,
     *      description="Not Found"
     *   ),
     *   @OA\Response(
     *      response=403,
     *      description="Forbidden"
     *   ),
     *   security={{"bearer_token": {}}}
     *)
     **/
    public function start(Request $request)
    {
        return response()->json(['message' => 'Selfie capture started.']);
    }

    /**
     * @OA\Post(
     ** path="/api/v1/brails-kyc/selfie/send",
     *   tags={"Brails KYC"},
     *   summary="Send Selfie Image",
     *   operationId="Send Selfie Image",
     *
     *   @OA\RequestBody(
     *      @OA\MediaType(mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"image_data"},
     *              @OA\Property(property="image_data", type="string", description="Selfie Image Data"),
     *          ),
     *      ),
     *   ),
     *
     *   @OA\Response(
     *      response=200,
     *      description="Success",
     *      @OA\MediaType(mediaType="application/json"),
     *   ),
     *   @OA\Response(
     *      response=401,
     *      description="Unauthenticated"
     *   ),
     *   @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     *   @OA\Response(
     *      response=404,
     *      description="Not Found"
     *   ),
     *   @OA\Response(
     *      response=403,
     *      description="Forbidden"
     *   ),
     *   security={{"bearer_token": {}}}
     *)
     **/
    public function stop(Request $request)
    {
        // Validate that image_data is present
        $validator = Validator::make($request->all(), [
            'image_data' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Invalid input data.'], 400);
        }

        // Capture the base64 encoded image data from the request
        $imageData = $request->input('image_data');

        // Decode the base64 image data
        $imageDecoded = base64_decode($imageData);

        // Create a temporary file with the decoded image data
        $tempFile = tmpfile();
        $filePath = stream_get_meta_data($tempFile)['uri'];
        fwrite($tempFile, $imageDecoded);

        try {
            // Upload the selfie to Cloudinary
            $uploadResult = Cloudinary::upload($filePath, [
                'folder' => 'leverpay/kyc',
                'public_id' => 'selfie_' . Auth::id() . '_' . time(),
            ]);

            // Get the URL of the uploaded image
            $uploadedUrl = $uploadResult->getSecurePath();

            // Get the authenticated user
            $user = Auth::user();
            $kycVerification = KycVerification::where('user_id', $user->id)->first();

            if ($kycVerification) {
                // Save the uploaded selfie URL to the database
                $kycVerification->live_face_verification = $uploadedUrl;
                $kycVerification->save();
            }

            // Broadcast the image data to connected clients (if needed)
            broadcast(new SelfieCaptured($uploadedUrl))->toOthers();

            // Return success response with the Cloudinary image URL
            return response()->json([
                'message' => 'Selfie captured and saved successfully.',
                'image_url' => $uploadedUrl
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to upload the selfie.', 'error' => $e->getMessage()], 500);
        } finally {
            // Close and remove the temporary file
            fclose($tempFile);
        }
    }
}
