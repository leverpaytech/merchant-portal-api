<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use App\Models\User;
#use App\Http\Controllers\HomeController;

Route::get('/', function ()
{
    $user = User::find(1);
        dd($user->wallet->amount);
    // return view('welcome');
});
#Route::get('/home', [HomeController::class, 'index'])->name('home');

Route::post('/', function(Request $request){
    Log::info(json_encode($request->all()));
    dd('post post');
});
