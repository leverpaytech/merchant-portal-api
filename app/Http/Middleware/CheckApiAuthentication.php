<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckApiAuthentication
{
    public function handle(Request $request, Closure $next)
    {
        \Log::info('Middleware Executed');
        
        if (Auth::guard('api')->check()) {
            return $next($request);
        }

        return response()->json(['error' => 'Unauthenticated.'], 401);
    }
}
