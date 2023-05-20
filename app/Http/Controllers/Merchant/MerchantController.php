<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\BaseController;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\ActivityLog;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class MerchantController extends BaseController
{
    protected $userModel;

    public function __construct(User $user)
    {
        $this->userModel = $user;
    }

    /**
     * @OA\Get(
     ** path="/api/v1/merchant/get-merchant-profile",
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
     *       {"bearer_token": {}}
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
     ** path="/api/v1/merchant/update-merchant-profile",
     *   tags={"Merchant"},
     *   summary="Update merchant profile",
     *   operationId="Update merchant profile",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"email","password", "first_name","last_name","address", "business_name", "phone", "state", "city"},
     *              @OA\Property( property="first_name", type="string"),
     *              @OA\Property( property="last_name", type="string"),
     *              @OA\Property( property="email", type="string"),
     *              @OA\Property( property="address", type="string"),
     *              @OA\Property( property="business_name", type="string"),
     *              @OA\Property( property="phone", type="string"),
     *              @OA\Property( property="password", type="string"),
     *              @OA\Property( property="state", type="string"),
     *              @OA\Property( property="city", type="string"),
     *              @OA\Property( property="passport", type="file"),
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
                $newname= $userId.''.time().'.'.$request->passport->extension();
                 $request->passport->move(public_path('passports'), $newname);

                $data['passport']= $newname;

                //add new image
                $request->passport->move(public_path('passports'), $newname);
            } catch (\Exception $ex) {
                return $this->sendError($ex->getMessage());
            }
        }

        $user = $this->userModel->updateUser($data,$userId);
        $data2['activity']="Merchant Profile Update";
        $data2['user_id']=$userId;

        ActivityLog::createActivity($data2);

        return $this->successfulResponse(new UserResource($user), 'Merchant profile successfully updated');
    }

    /**
     * @OA\Get(
     ** path="/api/v1/merchant/get-merchant-currencies}",
     *   tags={"Merchant"},
     *   summary="Get merchant's currencies",
     *   operationId="get merchant's currencies",
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
    public function getUserCurrencies(){
        return $this->successfulResponse(Auth::user()->currencies,'');
    }

    /**
     * @OA\Post(
     ** path="/api/v1/merchant/add-currencies",
     *   tags={"Merchant"},
     *   summary="Add merchant curriencies",
     *   operationId="Add merchant curriencies",
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
     *       {"bearer_token": {}}
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
