<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SwaggerUiController extends Controller
{
    public function index(Request $request)
    {
        $this->middleware(['auth:api', 'your_custom_middleware']);

        // Additional checks or actions here...

        $user = Auth::user();

        return view('swagger.index', ['user' => $user]);
    }
}


