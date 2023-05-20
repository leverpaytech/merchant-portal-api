<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use App\Models\Transaction;
#use App\Http\Controllers\HomeController;

Route::get('/', function ()
{
    $a = Transaction::find(1);
    dd($a->user->wallet);
    // Log::info(url()->full());
    // dd('get post');
    // dd(env('PAYSTACK_SECRET_TEST_KEY'));
    // return view('welcome');
});
#Route::get('/home', [HomeController::class, 'index'])->name('home');

Route::post('/', function(Request $request){
    Log::info(json_encode($request->all()));
    dd('post post');
});
