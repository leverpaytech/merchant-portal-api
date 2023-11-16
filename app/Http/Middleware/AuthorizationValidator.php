<?php

namespace App\Http\Middleware;

use App\Models\MerchantKeys;
use Closure;
use Illuminate\Http\Request;

class AuthorizationValidator
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
        $token = $request->bearerToken();
        if(empty($token)) {
            abort(400, 'Unauthorized request, invalid token');
        }
        $keys = MerchantKeys::where('live_secret_key', $token)->first();
        if(!$keys) {
            abort(400, 'Unauthorized request, check token and try again');
        }

        return $next($request);
    }
}
