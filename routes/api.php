<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use \App\Http\Controllers\AuthController;
use \App\Http\Controllers\User\UserController;
use \App\Http\Controllers\User\WalletController;

use \App\Http\Controllers\Merchant\MerchantController;
use \App\Http\Controllers\Merchant\AuthController as MerchantAuthController;
use \App\Http\Controllers\User\AuthController as UserAuthController;
//use \App\Http\Controllers\UserController;
use \App\Http\Controllers\ActivityLogController;
use \App\Http\Controllers\CurrencyController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\StateController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\KycController;


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


Route::prefix('v1')->group( function(){
    //get countries

    Route::post('/add-kyc', [KycController::class, 'addKyc']);
    Route::get('/kyc-details', [KycController::class, 'getKycDocument']);

    Route::get('/get-countries', [CountryController::class, 'index']);
    Route::post('/get-states', [StateController::class, 'index']);
    Route::post('/get-cities', [CityController::class, 'index']);


    Route::post('/login',[AuthController::class, 'login'])->name('login');
    Route::post('/resend-verification-email', [AuthController::class, 'resendVerificationEmail'])->name('verify-verification-email');
    Route::post('/verify-email', [AuthController::class, 'verifyEmail'])->name('verify-email');
    Route::post('forgot-password', [AuthController::class, 'sendForgotPasswordToken']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('reset-password');
    Route::get('/currencies', [CurrencyController::class, 'getCurrencies']);

    Route::get('verify-transaction', [WalletController::class, 'verifyTransaction']);

    Route::prefix('/merchant')->group( function(){

        Route::post('/signup', [MerchantAuthController::class, 'create'])->name('merchant.sign-up');


        Route::middleware(['auth:api', 'scopes:merchant'])->group( function () {
            Route::get('/logout', [MerchantAuthController::class, 'logout'])->name('merchant.logout');
            Route::get('/get-merchant-profile', [MerchantController::class, 'getMerchantProfile'])->name('merchant.get');
            Route::post('/update-merchant-profile', [MerchantController::class, 'updateMerchantProfile'])->name('merchant.update');


            Route::get('/get-merchant-currencies', [MerchantController::class, 'getUserCurrencies']);
            Route::post('/add-currencies', [MerchantController::class, 'addCurrencies']);

            Route::post('/get-merchant-keys', [MerchantController::class, 'getMerchantKeys']);
            Route::post('/change-mode', [MerchantController::class, 'changeMode']);

        });

    });

    Route::prefix('/user')->group( function() {
        Route::post('/signup', [UserAuthController::class, 'create'])->name('user.sign-up');

        Route::middleware('auth:api')->group( function () {
            Route::get('/logout', [UserAuthController::class, 'logout'])->name('user.logout');
            Route::get('/get-user-profile', [UserController::class, 'getUserProfile'])->name('user.get');
            Route::post('/update-user-profile', [UserController::class, 'updateUserProfile'])->name('user.update');


            Route::get('/get-user-currencies', [UserController::class, 'getUserCurrencies']);
            Route::post('/add-currencies', [UserController::class, 'addCurrencies']);
            Route::get('/get-wallet', [WalletController::class, 'getWallet']);

            Route::post('/fund-wallet',[WalletController::class, 'fundWallet']);

            // Route::post('/generate-card', [UserController::class, 'generateCard']);
            Route::get('/get-card', [UserController::class, 'getCard']);
        });
    });

    Route::prefix('/admin')->group(function(){
        Route::middleware('auth:api')->group( function () {
            Route::get('/get-all-merchants', [AdminController::class, 'getAllMerchants'])->name('merchants.all');
            Route::get('/get-all-users', [AdminController::class, 'getAllUsers'])->name('users.all');
            Route::post('/add-payment-option', [AdminController::class, 'createPaymentOption']);
            Route::post('/add-new-currency', [CurrencyController::class, 'create'])->name('create.currency');

            Route::get('/get-kyc-list', [AdminController::class, 'getKycs']);

        });
    });

    Route::prefix('/activities')->group( function() {
        Route::middleware('auth:api')->group( function () {
            Route::get('/logs', [ActivityLogController::class, 'index'])->name('activity.logs');
        });
    });

});



