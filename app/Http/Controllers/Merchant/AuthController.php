<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\BaseController;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Mail\SendEmailVerificationCode;
use App\Models\ActivityLog;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Validator;

class AuthController extends BaseController
{

    /**
     * @OA\Post(
     ** path="/api/merchant/register",
     *   tags={"Merchant"},
     *   summary="Create a new merchant account",
     *   operationId="create a new merchant",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"email","password", "name", "address", "phone", "state", "city"},
     *              @OA\Property( property="name", type="string"),
     *              @OA\Property( property="email", type="string"),
     *              @OA\Property( property="address", type="string"),
     *              @OA\Property( property="phone", type="string"),
     *              @OA\Property( property="password", type="string"),
     *              @OA\Property( property="state", type="string"),
     *              @OA\Property( property="city", type="string"),
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
     *       {"api_key": {}}
     *   }
     *)
     **/
    public function create(Request $request)
    {
        $data = $request->all();

        $validator = Validator::make($data, [
            'first_name' => 'required',
            'last_name' => 'required',
            'address' => 'required',
            'email' => 'unique:users,email|required|email',
            'phone' => 'unique:users',
            'business_name'=>'required',
            'state' => 'required',
            'city' => 'required',
            'password' => ['required', Password::min(8)->symbols()->uncompromised() ]
        ]);

        if ($validator->fails())
            return $this->sendError('Error',$validator->errors(),422);

        if (User::where('email',$request->email)->first())
            return $this->sendError('Merchant with same email exists',[],400);

        //$password = Str::random(6);
        //$data['password'] = Hash::make($password);

        $user = $this->createMerchant($data);

        $data2['activity']="Merchant Sign Up";
        $data2['user_id']=$user->id;

        ActivityLog::createActivity($data2);

        return $this->successfulResponse(new UserResource($user), 'Merchant successfully sign-up');
    }

    private function createMerchant($data)
    {
        // $data['status'] = 1;
        // $data['role_id'] = 3;

        $verifyToken = rand(1000,9999);

        $data['verify_email_token'] = $verifyToken;
        $data['password'] = bcrypt($data['password']);
        // $user = $this->userModel->createMerchant($data);
        $user = User::create($data);

        if($data['role_id'] == 1){
            $merchant = new Merchant();
            $merchant->user_id = $user->id;
            $merchant->business_name = $data['business_name'];
            $merchant->save();
        }

        // send email
        Mail::to($data['email'])->send(new SendEmailVerificationCode($data['first_name'].' '.$data['last_name'], $verifyToken));

        return $user;
    }
}
