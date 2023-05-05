<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Collection;
use App\Models\User;
use App\Http\Resources\GeneralResource;


class ActivityLogController extends BaseController
{
    /**
     * @OA\Get(
     ** path="/api/merchant/activities/activity-logs",
     *   tags={"Merchant"},
     *   summary="Get authenticated user logs",
     *   operationId="get authenticated user logs",
     *
     *   @OA\Response(
     *      response=200,
     *       description="Success",
     *     ),
     *   security={
     *       {"api_key": {}}
     *     }
     *)
     **/
    public function index()
    {
        $user =  Auth::user();
        if ($user->role_id == 1) {
            $get_logs = ActivityLog::latest()->take(50)->get();
        } else {
            $get_logs = ActivityLog::where(['user_id' => $user->id])->latest()->take(50)->get();
        }

        $result = [
            'activity_logs' => $get_logs->load('user'),
        ];
        
        $response = [
            'success' => true,
            'data' => $result,
            'message' => "Activity logs retrieved successfully."
        ];

        return response()->json($response, 200);
    }
}
