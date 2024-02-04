<?php

use App\Models\ExchangeRate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use App\Models\User;
#use App\Http\Controllers\HomeController;
use App\Http\Controllers\KycController;
use App\Models\Account;
use App\Services\SmsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\Uid\Ulid;
use Webpatser\Uuid\Uuid;
use Carbon\Carbon;

// Route::get('/', function (){

// });

// Route::get('/kycDetails', [KycController::class, 'getKycDocument'])->name('kycDetails');

// Route::get('upload', function(){
//     return view('upload');
// });

// Route::get('custom-clear-cache', function(){
//     \Artisan::call('route:cache');
//     \Artisan::call('config:cache');
//     \Artisan::call('cache:clear');
//     \Artisan::call('view:clear');
//     \Artisan::call('optimize:clear');
//     dd('done');
// });

// Route::post('/addKyc', [KycController::class, 'addKyc'])->name('addKyc');
