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
use Pusher\Pusher;

class SelfieController extends BaseController
{
    public function sendSelfie(Request $request)
    {
        $data = $request->input('image');
        $options = [
            'cluster' => env('PUSHER_APP_CLUSTER'),
            'useTLS' => true,
        ];
        $pusher = new Pusher(
            env('PUSHER_APP_KEY'),
            env('PUSHER_APP_SECRET'),
            env('PUSHER_APP_ID'),
            $options
        );

        $pusher->trigger('selfie-channel', 'new-selfie', ['image' => $data]);

        return response()->json(['status' => 'success']);
    }
}
