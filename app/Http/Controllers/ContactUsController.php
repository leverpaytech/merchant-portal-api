<?php

namespace App\Http\Controllers;
use App\Models\ContactUs;
use Illuminate\Http\Request;
use Webpatser\Uuid\Uuid;

class ContactUsController extends BaseController
{
    /**
     * @OA\Post(
     ** path="/api/v1/contact-us",
     *   tags={"ContactUs"},
     *   summary="Contact Us",
     *   operationId="Contact Us",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"email","subject","message"},
     *              @OA\Property( property="email", type="string"),
     *              @OA\Property( property="subject", type="string"),
     *              @OA\Property( property="message", type="string")
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
    public function submitForm(Request $request)
    { 
        $data = $this->validate($request, [
            'email'=>'required|string',
            'subject'=>'required|string',
            'message'=>'required|string'
        ]);

        $data['uuid'] = Uuid::generate()->string;
        $contact=ContactUs::create($data);

        return $this->successfulResponse($contact, 'Message successfully sent');
    }
}
