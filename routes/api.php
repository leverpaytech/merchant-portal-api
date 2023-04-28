<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use \App\Http\Controllers\AuthController;
use \App\Http\Controllers\UserController;
use \App\Http\Controllers\ActivityLogController;
use \App\Http\Controllers\CurrencyController;
use App\Http\Controllers\Admin\AdminController;


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


Route::prefix('/merchant')->group( function() {
    Route::post('/login',[AuthController::class, 'login'])->name('login');
    Route::post('/register', [UserController::class, 'create'])->name('merchant.sign-up');
    Route::post('/resend-verification-email', [AuthController::class, 'resendVerificationEmail'])->name('verify-verification-email');
    Route::get('/verify-email', [AuthController::class, 'verifyEmail'])->name('verify-email');
    Route::post('forgot-password', [AuthController::class, 'sendForgotPasswordToken']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('reset-password');

    Route::middleware('auth:api')->group( function () {
        Route::get('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/get/{id}', [UserController::class, 'get'])->name('merchant.get');
        Route::put('/update-merchant-profile', [UserController::class, 'updateUser'])->name('merchant.update');
        Route::get('/', [UserController::class, 'index'])->name('merchants.all');

        Route::get('/currencies', [UserController::class, 'getCurrencies']);
        Route::get('/get-user-currencies', [UserController::class, 'getUserCurrencies']);
        Route::post('/add-currencies', [UserController::class, 'addCurrencies']);


        Route::prefix('/activities')->group( function() {
            Route::get('/logs', [ActivityLogController::class, 'index'])->name('activity.logs');
        });
    });

});

Route::prefix('admin')->group(function(){
    Route::middleware('auth:api')->group( function () {
        Route::post('/payment-option', [AdminController::class, 'createPaymentOption']);
        Route::post('/currencies', [CurrencyController::class, 'create'])->name('create.currency');
    });
});





