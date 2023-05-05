<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Currency;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendEmailVerificationCode;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;

class UserController extends BaseController
{
    protected $userModel;

    public function __construct(User $user)
    {
        $this->userModel = $user;
    }
    /****************************merchants services****************************/
    /**
     * @OA\Get(
     ** path="/api/admin/get-all-merchants",
     *   tags={"Admin"},
     *   summary="Get all merchants",
     *   operationId="get all merchants",
     *
     *   @OA\Response(
     *      response=200,
     *       description="Success",
     *     ),
     *     security={
     *       {"api_key": {}}
     *     }
     *
     *)
     **/
    public function index()
    {
        return $this->successfulResponse(User::where('role_id',3)->get(), "Merchants retrieved successfully");
    }


    /**
     * @OA\Get(
     ** path="/api/merchant/get-merchant-profile",
     *   tags={"Merchant"},
     *   summary="Get merchant profile",
     *   operationId="get merchant profile details",
     * 
     *
     *   @OA\Response(
     *      response=200,
     *       description="Success",
     *     ),
     *     security={
     *       {"api_key": {}}
     *     }
     *
     *)
     **/
    public function getMerchantProfile()
    {
        $userId = Auth::user()->id;
        $user = User::where('id', $userId)->with('currencies')->get()->first();

        if(!$user)
            return $this->sendError('Merchant not found',[],404);
        return $this->successfulResponse($user,'Merchant found successfully');
    }

    /**
     * @OA\Post(
     ** path="/api/merchant/update-merchant-profile",
     *   tags={"Merchant"},
     *   summary="Update merchant profile",
     *   operationId="Update merchant profile",
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
     *              @OA\Property( property="passport", type="string"),
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
    public function updateMerchantProfile(Request $request)
    {
        $userId = Auth::user()->id;

        $data = $request->all();

        /*$validator = Validator::make($data, [
            'name' => 'required',
            'address' => 'required',
            'email' => 'required',
            'phone' => 'required',
            'state' => 'required',
            'city' => 'nullable',
            'passport' => 'nullable'
        ]);*/

        if(!$user = User::find($userId))
            return $this->sendError('User not found',[],404);
        if(isset($request->email))
        {
            $validator = Validator::make($request->all(),[
                'email' =>  Rule::unique('users')->ignore($userId),
                //'unique:users,email|required|email',
            ]);
            if ($validator->fails())
                return $this->sendError('Merchant with the same email exists already',$validator->errors(),400);

        }
        if(isset($request->passport))
        {
            try
            {
                $newname= $id.''.time().'.'.$request->passport->extension();
                 $request->passport->move(public_path('passports'), $newname);
           
                $data['passport']= $newname;
               
                //add new image
                $request->passport->move(public_path('passports'), $nimgname);
            } catch (\Exception $ex) {
                return $this->sendError($ex->getMessage());
            } 
        }
  
        $user = $this->userModel->updateUser($data,$userId);
        $data2['activity']="Profile Update";
        $data2['user_id']=$userId;

        ActivityLog::createActivity($data2);

        return $this->successfulResponse(new UserResource($user), 'Merchant profile successfully updated');
    }

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
            'name' => 'required',
            'address' => 'required',
            'email' => 'unique:users,email|required|email',
            'phone' => 'unique:users',
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

        $data2['activity']="Sign Up";
        $data2['user_id']=$user->id;

        ActivityLog::createActivity($data2);

        return $this->successfulResponse(new UserResource($user), 'Merchant successfully sign-up');
    }

    private function createMerchant($data)
    {
        $data['status'] = 1;
        $data['role_id'] = 3;

        $verifyToken = bin2hex(random_bytes(15));
        $verifyLink = env('FRONTEND_BASE_URL').'/verify-email?token='.$verifyToken;

        $data['verify_email_token'] = $verifyToken;
        $data['password'] = bcrypt($data['password']);
        $user = $this->userModel->createMerchant($data);

        // send email
        Mail::to($data['email'])->send(new SendEmailVerificationCode($data['name'], $verifyLink));


        return $user;
    }

    /**
     * @OA\Get(
     ** path="/api/merchant/currencies}",
     *   tags={"Merchant"},
     *   summary="Get all currencies",
     *   operationId="get all currencies",
     *
     *   @OA\Response(
     *      response=200,
     *       description="Success",
     *     ),
     *     security={
     *       {"api_key": {}}
     *     }
     *
     *)
     **/
    public function getCurrencies()
    {
        $currency = Currency::where('status', 1)->get();
        return $this->successfulResponse($currency,'');
    }

    /**
     * @OA\Get(
     ** path="/api/merchant/get-user-currencies}",
     *   tags={"Merchant"},
     *   summary="Get user currencies",
     *   operationId="get user currencies",
     *
     *   @OA\Response(
     *      response=200,
     *       description="Success",
     *     ),
     *     security={
     *       {"api_key": {}}
     *     }
     *
     *)
     **/
    public function getUserCurrencies(){
        return $this->successfulResponse(Auth::user()->currencies,'');
    }

    /**
     * @OA\Post(
     ** path="/api/merchant/add-currencies",
     *   tags={"Merchant"},
     *   summary="Create a user curriencies",
     *   operationId="create a user new curriencies",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"curriencies"},
     *              @OA\Property( property="curriencies", type="array",@OA\Items( type="integer"), )
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
    public function addCurrencies(Request $request)
    {
        $this->validate($request, [
            'currencies'=>'required|array'
        ]);
        $user = Auth::user()->currencies()->sync($request['currencies']);
        return $this->successfulResponse(Auth::user()->currencies,'');
    }

    
}
