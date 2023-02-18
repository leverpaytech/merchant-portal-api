<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use \App\Http\Controllers\AuthController; 
use \App\Http\Controllers\UserController; 
use \App\Http\Controllers\ActivityLogController; 
use \App\Http\Controllers\CurrencyController; 


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/


Route::prefix('/user')->group( function() {
    Route::post('/login',[AuthController::class, 'login'])->name('login');
    Route::post('/register', [UserController::class, 'create'])->name('merchant.sign-up');
    
    Route::middleware('auth:api')->group( function () {
        Route::get('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/get/{id}', [UserController::class, 'get'])->name('merchant.get');
    });
});

Route::prefix('/admin-dashboard')->group( function() {
    Route::middleware('auth:api')->group( function () {
        Route::get('/', [UserController::class, 'index'])->name('merchants.all');
    });
});

Route::prefix('/activities')->group( function() {
    Route::middleware('auth:api')->group( function () {
        Route::get('/logs', [ActivityLogController::class, 'index'])->name('activity.logs');
    });
});

Route::prefix('/currencies')->group( function() {
    Route::middleware('auth:api')->group( function () {
        Route::post('/', [CurrencyController::class, 'create'])->name('create.currency');
    });
});

Route::prefix('/payment-option')->group( function() {
    Route::middleware('auth:api')->group( function () {
        Route::put('/{uuid}/currencies', [PaymentOptionController::class, 'create'])->name('create.currency');
    });
});

