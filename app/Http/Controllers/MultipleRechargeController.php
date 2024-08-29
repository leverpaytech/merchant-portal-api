namespace App\Http\Controllers;

use App\Models\RechargeTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Services\{QuickTellerService,WalletService,ZeptomailService};
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Models\{BillPaymentPin,BillPaymentHistory,Wallet,ActivityLog,Transaction,QuickTellerDiscount};
use Webpatser\Uuid\Uuid;

class MultipleRechargeController extends Controller
{
    /**
     * @OA\Post(
     ** path="/api/v1/user/multiple-recharge/save-template",
     *   tags={"Multiple Recharge"},
     *   summary="Create or Update Template",
     *   operationId="Create or Update Template",
     *
     *    @OA\RequestBody(
     *      @OA\MediaType( mediaType="multipart/form-data",
     *          @OA\Schema(
     *              required={"phone_numbers"},
     *              @OA\Property( property="phone_numbers", type="array", @OA\Items(type="string") ),
     *              @OA\Property( property="total_amount", type="number", format="float", description="Total amount"),
     *              @OA\Property( property="name", type="string", description="Name"),
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
    public function saveTemplate(Request $request)
    {
        //$data = $request->validated();
        $data = $request->all();

        $validator = Validator::make($data, [
            'phone_numbers' => 'required|array',
            'phone_numbers.*.number' => 'required|string',
            'phone_numbers.*.network' => 'required|string',
            'phone_numbers.*.amount' => 'nullable|numeric',
            'total_amount' => 'required|numeric',
            'name' => 'required|string',
        ]);

        $user = Auth::user();
        if(!$user->id)
            return $this->sendError('Unauthorized Access',[],401);

        $userId = $user->id;

        $template = RechargeTemplate::updateOrCreate(
            ['uuid' => Uuid::generate()->string],
            ['user_id' => $userId],
            ['name' => $request->input('name')],
            ['template_data' => $request->input('template_data')]
        );

        return response()->json(['message' => 'Template saved successfully', 'template' => $template], 200);
    }

    public function rechargeMultipleNumbers(Request $request)
    {
        if($validator->fails())
        {
            return $this->sendError('Error',$validator->errors(),422);
        }

        $user = Auth::user();
        if(!$user->id)
            return $this->sendError('Unauthorized Access',[],401);

        $userId = $user->id;

        // Check account balance logic here
        $checkBalance = $this->checkWalletBalance($userId, $data['total_amount']);
        if (!$checkBalance) 
        {
            return response()->json('Insufficient wallet balance', 422);
        }

        $checkPin = $this->checkPinValidity($userId, $data['pin']);
        if (!$checkPin) {
            return response()->json('Invalid pin', 422);
        }

        // Proceed with recharge and send email to admin
        $this->processRecharge($data);

        // Notify user
        return response()->json(['message' => 'Recharge successful', 'cashback' => $this->calculateCashback($data['total_amount'])], 200);
    }

    public function saveTemplate(Request $request)
    {
        // Save template logic here
        $template = RechargeTemplate::create($request->all());

        return response()->json(['message' => 'Template saved successfully', 'template' => $template], 200);
    }

    protected function checkWalletBalance($userId, $amount)
    {
        $checkBalance = Wallet::where('user_id', $userId)->first(['withdrawable_amount', 'amount']);

        return $checkBalance && $checkBalance->withdrawable_amount >= $amount;
    }

    protected function processRecharge($data)
    {
        // Implement the recharge logic and send email to admin
        Mail::to('admin@example.com')->send(new \App\Mail\RechargeNotification($data));
    }

    protected function calculateCashback($totalAmount)
    {
        // Implement cashback calculation logic
        return $totalAmount * 0.05; // Example: 5% cashback
    }
}
