<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use App\Models\User;
#use App\Http\Controllers\HomeController;
use App\Http\Controllers\KycController;

Route::get('/', function ()
{
    //$user = User::find(1);
        //dd($user->wallet->amount);
        //return view('welcome');
});

Route::get('/kycDetails', [KycController::class, 'getKycDocument'])->name('kycDetails');

Route::get('upload', function(){
    return view('upload');
});


Route::post('/addKyc', [KycController::class, 'addKyc'])->name('addKyc');