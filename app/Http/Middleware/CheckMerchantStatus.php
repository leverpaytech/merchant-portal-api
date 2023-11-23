<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckMerchantStatus
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        if(Auth::user()->role_id != 1){
            abort(400, "Invalid merchant account, Please login again.");
        }
        if(Auth::user()->role_id == 1 && Auth::user()->kyc_status == 0) {
            abort(400, "Merchant account has not been activated, Please update your kyc to be activated");
        }
        return $next($request);
    }
}
