<?php

use App\Models\ExchangeRate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use App\Models\User;
#use App\Http\Controllers\HomeController;
use App\Http\Controllers\KycController;
use App\Models\Transaction;
use App\Services\SmsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

Route::get('/', function (){

    // DB::table('admin_logins')->insert([
    //     [
    //         'first_name' => 'LeverPay',
    //         'last_name' => 'Admin',
    //         'email' => 'ilelaboyealekan@gmail.com',
    //         'password' => Hash::make('password@.2023'),
    //         'phone' => '08102721331',
    //         'gender' => 'Male'
    //     ]
    // ]);
    //     dd('done');

    // $getExchageRate=Transaction::latest()->with('user')->get();
    // dd($getExchageRate);
    // // dd(Str::uuid()->toString());
    // // $user = User::find(1);
    // // dd($user);
    // dd(env('DB_USERNAME'));
    //     //dd($user->wallet->amount);
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
