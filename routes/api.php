<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use \App\Http\Controllers\AuthController;
use \App\Http\Controllers\User\UserController;
use \App\Http\Controllers\User\CardController;
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
use \App\Http\Controllers\Merchant\InvoiceController;
use \App\Http\Controllers\BankController;

use \App\Http\Controllers\Admin\AdminLoginController as AdminAuthController;


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

    //

    //Route::get('/get-account-no', [UserController::class, 'generateAccNo']);
    //Route::get('/on-boarding', [UserController::class, 'onBoarding']);


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

            Route::post('/create-invoice', [InvoiceController::class, 'createInvoice']);
            Route::get('/product/{uuid}', [InvoiceController::class, 'getInvoice']);

        });

    });
    Route::post('/set-pin', [CardController::class, 'setPin']);
    Route::prefix('/user')->group( function() {
        Route::post('/signup', [UserAuthController::class, 'create'])->name('user.sign-up');
        Route::get('/get-all-banks', [BankController::class, 'getBanks'])->name('get-all-banks');

        Route::middleware('auth:api')->group(function () {
            Route::get('/logout', [UserAuthController::class, 'logout'])->name('user.logout');
            Route::get('/get-user-profile', [UserController::class, 'getUserProfile'])->name('user.get');
            Route::post('/update-user-profile', [UserController::class, 'updateUserProfile'])->name('user.update');


            Route::get('/get-user-currencies', [UserController::class, 'getUserCurrencies']);
            Route::post('/add-currencies', [UserController::class, 'addCurrencies']);
            Route::get('/get-wallet', [WalletController::class, 'getWallet']);

            Route::post('/fund-wallet',[WalletController::class, 'fundWallet']);

            // Route::post('/generate-card', [UserController::class, 'generateCard']);
            Route::get('/get-card', [CardController::class, 'getCard']);
            Route::post('/set-pin', [CardController::class, 'setPin']);
            // Route::post('/upgrade-card', [CardController::class, 'upgradeCard']);
            Route::get('/get-user-transactions', [WalletController::class, 'getUserTransaction']);

            Route::post('search-user', [UserController::class, 'searchUser']);
            Route::post('transfer', [WalletController::class, 'transfer']);

            Route::post('submit-topup-request', [WalletController::class, 'submitTopupRequest']);
            Route::get('get-topup-requests', [WalletController::class, 'getTopupRequests']);

            Route::post('add-bank-account', [UserController::class, 'addBankAccount']);
            Route::get('get-user-bank-account', [UserController::class, 'getUserBankAccount']);

        });
    });

    Route::prefix('/admin')->group(function()
    {
        Route::post('/admin-login',[AdminAuthController::class, 'login'])->name('admin-login');
        Route::post('/admin-forgot-password', [AdminAuthController::class, 'sendForgotPasswordToken']);
        Route::post('/admin-verify-email', [AdminAuthController::class, 'resetPasswordVerify']);
        Route::post('/admin-reset-password', [AdminAuthController::class, 'resetPassword']);

        Route::middleware('auth:api')->group( function ()
        {
            Route::get('/admin-logout', [AdminAuthController::class, 'logout']);
            Route::get('/admin-profile', [AdminAuthController::class, 'adminProfile'])->name('admin-profile');
            Route::get('/get-all-merchants', [AdminController::class, 'getAllMerchants'])->name('merchants.all');
            Route::get('/get-all-users', [AdminController::class, 'getAllUsers'])->name('users.all');
            Route::get('/get-kyc-list', [AdminController::class, 'getKycs']);

            Route::post('/add-payment-option', [AdminController::class, 'createPaymentOption']);
            Route::get('/get-payment-options', [AdminController::class, 'getPaymentOption']);
            Route::post('/add-new-currency', [CurrencyController::class, 'create'])->name('create.currency');
            Route::post('/add-exchange-rate', [AdminController::class, 'addExchangeRate'])->name('add-exchange-rate');
            Route::get('/active-exchange-rate', [AdminController::class, 'activeExchangeRate'])->name('active-exchange-rate');
            Route::get('/all-exchange-rate', [AdminController::class, 'allExchangeRate'])->name('all-exchange-rate');
            
        });
    });

    Route::prefix('/activities')->group( function() {
        Route::middleware('auth:api')->group( function () {
            Route::get('/logs', [ActivityLogController::class, 'index'])->name('activity.logs');
        });
    });

});



