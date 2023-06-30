<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use App\Models\User;
#use App\Http\Controllers\HomeController;
use App\Http\Controllers\KycController;

Route::get('/', function (){
    $user = User::find(1);
    return($user);
        //dd($user->wallet->amount);
        //return view('welcome');
        // dd('ddd');
        // dd(env('PAYSTACK_SECRET_TEST_KEY'));
});

Route::get('/kycDetails', [KycController::class, 'getKycDocument'])->name('kycDetails');

Route::get('upload', function(){
    return view('upload');
});

Route::get('custom-clear-cache', function(){
    \Artisan::call('route:cache');
    \Artisan::call('config:cache');
    \Artisan::call('cache:clear');
    \Artisan::call('view:clear');
    \Artisan::call('optimize:clear');
    dd('done');
});

Route::post('/addKyc', [KycController::class, 'addKyc'])->name('addKyc');
