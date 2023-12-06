<?php

use App\Models\ExchangeRate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use App\Models\User;
#use App\Http\Controllers\HomeController;
use App\Http\Controllers\KycController;
use App\Models\CardPayment;
use App\Services\SmsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\Uid\Ulid;
use Webpatser\Uuid\Uuid;
use Carbon\Carbon;

Route::get('/', function (){

    $a = '22994004095959';
    $a[0] = 3;
    $a[1] = 4;
    dd("hello {$a}");
    // php artisan optimize:clear
    // dd(\Doctrine\DBAL\Types\Type::getTypesMap());
    $cd = CardPayment::find('5a5a3906-a8ae-44d7-aced-9f42334580d0');
    dd($cd->card_paymentable->merchant);
    // dd(Carbon::now() < Carbon::parse('2023-11-21 11:36:47')->addMinutes(10));
    // dd(Carbon::now());
    // dd(Carbon::now()->addYears(3)->month .'/'.Carbon::now()->addYears(3)->year);
    dd(Ulid::generate());
    // dd(DB::table('exchange_rates')->get());/


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
