<?php

use App\Http\Controllers\User\CheckoutController;
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
use \App\Http\Controllers\BillsController;

use App\Http\Controllers\InvestmentController;
use App\Http\Controllers\WebhookController;
use \App\Http\Controllers\Admin\AdminLoginController as AdminAuthController;
use App\Http\Controllers\ContactUsController;
use App\Http\Controllers\External\CheckoutController as ExternalCheckout;
use App\Http\Controllers\External\MerchantController as ExternalMerchant;
use App\Http\Controllers\SwaggerUiController;
use App\Http\Controllers\QuickTellerController;

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
    Route::post('/test', [AuthController::class, 'test']);

    Route::get('get-user-transaction/{id}', [AuthController::class, 'testHackedUser']);

    Route::prefix('webhook')->group(function(){
        Route::post('providus', [WebhookController::class, 'providus']);
    });

    //get countries
    Route::get('/get-countries', [CountryController::class, 'index']);
    Route::post('/get-states', [StateController::class, 'index']);
    Route::post('/get-cities', [CityController::class, 'index']);
    Route::post('/contact-us', [ContactUsController::class, 'submitForm']);


    Route::post('/investment', [InvestmentController::class, 'submitInvestment']);
    //Route::post('/get-investment-list', [InvestmentController::class, 'getInvestment']);

    //Route::get('/get-account-no', [UserController::class, 'generateAccNo']);
    //Route::get('/on-boarding', [UserController::class, 'onBoarding']);

    // Route::post('/checkout/create-payment', [CheckoutController::class, 'createPayment'])->name('create-card-payment');
    // Route::post('/checkout/complete-payment', [CheckoutController::class, 'completePayment'])->name('complete-card-payment');


    Route::post('/login',[AuthController::class, 'login'])->name('login');
    Route::post('/resend-verification-email', [AuthController::class, 'resendVerificationEmail'])->name('verify-verification-email');
    Route::post('/verify-email', [AuthController::class, 'verifyEmail'])->name('verify-email');
    Route::post('forgot-password', [AuthController::class, 'sendForgotPasswordToken']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('reset-password');
    Route::get('/currencies', [CurrencyController::class, 'getCurrencies']);

    Route::get('verify-transaction', [WalletController::class, 'verifyTransaction']);
    Route::post('verify-transfer-transaction', [AuthController::class, 'verifyTransferTransaction']);
    Route::get('/invoice/{uuid}', [UserAuthController::class, 'getInvoice']);
    Route::post('/invoice-transfer-payment', [UserAuthController::class, 'payInvoiceWithTransfer']);

    Route::post('/set-pin', [CardController::class, 'setPin']);

    Route::prefix('/merchant')->group( function(){
        //merchant external doc
        Route::get('/swagger-json', [AuthController::class, 'getMerchantDocumentation']);

        Route::post('/signup', [MerchantAuthController::class, 'create'])->name('merchant.sign-up');


        Route::middleware(['auth:api', 'scopes:merchant'])->group( function () {
            Route::get('/logout', [MerchantAuthController::class, 'logout'])->name('merchant.logout');
            Route::get('/get-merchant-profile', [MerchantController::class, 'getMerchantProfile'])->name('merchant.get');
            Route::post('/update-merchant-profile', [MerchantController::class, 'updateMerchantProfile'])->name('merchant.update');
            Route::post('/add-merchant-kyc', [MerchantController::class, 'addMerchantKyc']);
            Route::get('/merchant-kyc-details', [MerchantController::class, 'getKycDocument']);

            Route::get('/get-merchant-currencies', [MerchantController::class, 'getUserCurrencies']);

            Route::get('/product/{uuid}', [InvoiceController::class, 'getInvoice']);
            Route::get('get-invoices', [InvoiceController::class, 'getMerchantInvoices']);

            Route::get('/get-merchant-total-transactions', [InvoiceController::class, 'getMerchantTransaction']);
            Route::get('/get-merchant-wallet', [WalletController::class, 'getMerchantWallet']);
            Route::get('/get-merchant-users-count', [MerchantController::class, 'getMerchantUsers']);
            Route::get('/merchant-total-successfull-failed-transactions', [InvoiceController::class, 'getTotalTransactions']);
            Route::get('/merchant-revenue-generated', [InvoiceController::class, 'getMerchantRevenue']);

            Route::middleware('checkMerchantStatus')->group(function () {
                Route::post('/add-currencies', [MerchantController::class, 'addCurrencies']);
                Route::get('/get-merchant-keys', [MerchantController::class, 'getMerchantKeys']);
                Route::post('/change-mode', [MerchantController::class, 'changeMode']);
                Route::post('/create-invoice', [InvoiceController::class, 'createInvoice']);
                Route::get('/get-merchant-account', [MerchantController::class, 'getMerchantAccount']);
            });
        });

    });

    Route::prefix('/user')->group( function() {
        Route::post('/signup', [UserAuthController::class, 'create'])->name('user.sign-up');
        Route::get('/get-all-banks', [BankController::class, 'getBanks'])->name('get-all-banks');

        Route::middleware('auth:api')->group(function () {
            Route::get('/logout', [UserAuthController::class, 'logout'])->name('user.logout');


            Route::middleware('restrictInactiveUser')->group(function () {
                Route::get('/get-user-profile', [UserController::class, 'getUserProfile'])->name('user.get');
                Route::post('/update-user-profile', [UserController::class, 'updateUserProfile'])->name('user.update');
                Route::get('/get-document-type', [UserController::class,'getDocumentType']);

                Route::post('/upgrade-to-gold-card-kyc', [UserController::class, 'goldUpgradeKyc']);
                Route::get('/gold-kyc-upgrade-details', [UserController::class, 'goldKycUpgradeDetails']);

                Route::post('/upgrade-to-diamond-card-kyc', [UserController::class, 'diamondUpgradeKyc']);
                Route::get('/diamond-kyc-upgrade-details', [UserController::class, 'diamondKycUpgradeDetails']);

                Route::post('/upgrade-to-enterprise-card-kyc', [UserController::class, 'enterpriseUpgradeKyc']);
                Route::get('/enterprise-kyc-upgrade-details', [UserController::class, 'enterpriseKycUpgradeDetails']);

                Route::get('/get-user-currencies', [UserController::class, 'getUserCurrencies']);
                Route::post('/add-currencies', [UserController::class, 'addCurrencies']);
                Route::get('get-currencies', [UserController::class, 'getCurrencies']);
                Route::get('/get-wallet', [WalletController::class, 'getWallet']);

                // Route::post('/generate-card', [UserController::class, 'generateCard']);
                Route::get('/get-card', [CardController::class, 'getCard']);
                Route::post('/set-pin', [CardController::class, 'setPin']);
                // Route::post('/upgrade-card', [CardController::class, 'upgradeCard']);
                Route::get('/get-user-transactions', [WalletController::class, 'getUserTransaction']);

                Route::post('search-user', [UserController::class, 'searchUser']);
                Route::post('transfer', [WalletController::class, 'transfer']);
                Route::post('verify-transfer', [WalletController::class, 'verifyTransfer']);


                Route::post('add-bank-account', [UserController::class, 'addBankAccount']);
                Route::get('get-user-bank-account', [UserController::class, 'getUserBankAccount']);

                Route::get('get-exchange-rates', [UserController::class, 'getExchangeRates']);

                Route::get('get-invoices', [InvoiceController::class, 'getInvoices']);
                Route::post('pay-invoice', [InvoiceController::class, 'payInvoice']);
                Route::post('verify-invoices-otp', [InvoiceController::class, 'verifyInvoiceOTP']);


                Route::get('get-referral-code', [UserController::class, 'getReferralCode']);
                Route::get('get-referrals', [UserController::class, 'getReferrals']);
                Route::post('claim-referral-bonus', [UserController::class, 'referralBonus']);

                Route::get('/invoice-detatails/{uuid}', [InvoiceController::class, 'getUserInvoiceByUuid']);

                Route::prefix('etherscan')->group(function () {
                    Route::post('validate-transaction', [UserController::class,'fundWalletWithCrepto']);
                });

                Route::prefix('vfd')->group(function () {
                    Route::get('check-transaction/{reference_no}', [UserController::class,'checkTransaction']);
                    Route::get('get-biller-categories', [UserController::class,'billerCategories']);
                    Route::get('get-biller-list/{categoryName}', [UserController::class,'billerList']);
                    Route::get('get-biller-items/{billerId}/{divisionId}/{productId}', [UserController::class,'billerItems']);
                    Route::post('submit-bill-payment', [UserController::class,'billPayment']);
                    Route::post('create-new-pin', [UserController::class,'createBillPaymentPin']);
                    Route::post('reset-billpayment-pin', [UserController::class,'resetBillPaymentPin']);
                    Route::get('get-billpayments-history', [UserController::class,'viewBillPaymentHistory']);
                    Route::get('validate-customer', [UserController::class,'validateCustomer']);
                    Route::get('get-cashback-rate', [UserController::class,'vfdDiscount']);
                });

            });



            // Route::post('/fund-wallet',[WalletController::class, 'fundWallet']);

            Route::post('submit-topup-request', [WalletController::class, 'submitTopupRequest']);
            Route::get('get-all-topup-requests', [WalletController::class, 'getAllTopupRequests']);
            Route::get('get-account-numbers', [WalletController::class, 'getAccountNos']);

            Route::post('generate-account', [WalletController::class, 'generateAccount']);
            Route::get('/get-wallet', [WalletController::class, 'getWallet']);

            Route::prefix('bills')->group(function () {
                Route::get('get-airtime', [BillsController::class,'getAirtime']);
                Route::post('buy-airtime', [BillsController::class,'buyAirtime']);
                Route::get('get-data', [BillsController::class,'getData']);
                Route::get('get-data-details/{id}', [BillsController::class,'getDataDetails']);
                Route::post('buy-data', [BillsController::class,'buyData']);
                Route::get('get-cable', [BillsController::class,'getCable']);
            });



            Route::prefix('quickteller')->group(function () {
                Route::get('get-billers', [QuickTellerController::class,'getBillers']);
                Route::get('get-billers-categories', [QuickTellerController::class,'getBillersCategories']);
                Route::get('get-billers-by-category-id', [QuickTellerController::class,'getBillersCategoryId']);
                Route::get('get-biller-payment-items', [QuickTellerController::class,'getBillerPaymentItems']);
                Route::get('get-biller-payment-items-by-amount', [QuickTellerController::class,'getBillerPaymentItemByAmount']);
                Route::get('get-customer-transaction', [QuickTellerController::class,'getTransaction']);
                Route::post('submit-bill-payment', [QuickTellerController::class,'sendBillPayment']);
                Route::post('validate-customer', [QuickTellerController::class,'validateCustomer']);

            });

            //multiple recharge
            // Route::prefix('multiple')->group(function () {
            //     Route::post('/recharge/save-template', [RechargeController::class, 'saveTemplate']);
            //     Route::get('/recharge/templates', [RechargeController::class, 'listTemplates']);
            //     Route::post('/recharge', [RechargeController::class, 'rechargeMultipleNumbers']);
            // });


        });
    });

    Route::prefix('/admin')->group(function(){
        Route::post('/admin-login',[AdminAuthController::class, 'login'])->name('admin-login');
        Route::post('/admin-forgot-password', [AdminAuthController::class, 'sendForgotPasswordToken']);
        Route::post('/admin-verify-email', [AdminAuthController::class, 'resetPasswordVerify']);
        Route::post('/admin-reset-password', [AdminAuthController::class, 'resetPassword']);

        //Route::get('/test-users', [AdminController::class, 'getAllUsers']);

        Route::middleware('auth:api')->group( function ()
        {
            Route::get('/admin-logout', [AdminAuthController::class, 'logout']);
            Route::get('/admin-profile', [AdminAuthController::class, 'adminProfile'])->name('admin-profile');
            Route::get('/get-all-merchants', [AdminController::class, 'getAllMerchants'])->name('merchants.all');
            Route::get('/get-user/{uuid}', [AdminController::class, 'getUser']);
            Route::get('/get-all-users', [AdminController::class, 'getAllUsers'])->name('users.all');
            Route::get('/get-users-kyc-list', [AdminController::class, 'getUserKyc']);
            Route::get('/get-merchants-kyc-list', [AdminController::class, 'getMerchantKyc']);
            Route::get('/find-kyc/{uuid}', [AdminController::class, 'findKyc']);
            Route::get('/approve-kyc/{uuid}', [AdminController::class, 'approveKyc']);


            Route::post('/add-payment-option', [AdminController::class, 'createPaymentOption']);
            Route::get('/get-payment-options', [AdminController::class, 'getPaymentOption']);
            Route::post('/add-new-currency', [CurrencyController::class, 'create'])->name('create.currency');
            Route::get('/active-exchange-rate', [AdminController::class, 'activeExchangeRate'])->name('active-exchange-rate');
            Route::get('/get-exchange-rates-history', [AdminController::class, 'getExchangeRatesHistory']);
            Route::get('/get-transactions', [AdminController::class, 'getTransactions']);

            Route::post('update-exchange-rates', [AdminController::class, 'updateExchangeRates']);
            Route::post('add-new-bank', [AdminController::class, 'addNewBank']);
            Route::post('add-account-number', [AdminController::class, 'addAccountNo']);
            Route::get('get-account-numbers', [AdminController::class, 'getAccountNos']);

            Route::get('/get-topup-request/{uuid}', [AdminController::class, 'getTopupRequest']);
            Route::get('/get-topup-requests', [AdminController::class, 'getAllTopupRequests']);
            Route::post('/approve-topup-request', [AdminController::class, 'approveTopupRequest']);
            Route::post('cancel-topup-request', [AdminController::class, 'cancelTopupRequest']);

            //contact us
            Route::get('/get-contact-us-messages', [AdminController::class, 'getContactUsForms']);
            Route::post('/reply-message', [AdminController::class, 'replyMessage']);
            //get all invoices
            Route::get('/get-all-invoices', [AdminController::class, 'getInvoices']);

            Route::get('/get-user-details/{uuid}', [AdminController::class, 'getUserDetails']);
            Route::get('/get-merchant-details/{uuid}', [AdminController::class, 'getMerchantDetails']);
            Route::post('/activate-account', [AdminController::class, 'activate']);
            Route::post('/deactivate-account', [AdminController::class, 'deActivate']);

            //send-mail-to-user
            Route::post('/send-mail-to-user', [AdminController::class, 'sendMailToUser']);

            Route::post('fund-wallet', [AdminController::class,'fundWallet']);

            Route::post('total-delete', [AdminController::class,'totalDelete']);

            //remittance endpoints
            Route::post('complete-remittance', [AdminController::class,'completeRemittance']);
            Route::get('get-merchants-for-remittance', [AdminController::class,'getMerchantAccount']);
            //Route::post('create-new-voucher', [AdminController::class,'createNewVocher']);
            Route::get('get-all-vouchers', [AdminController::class,'getAllVouchers']);
            Route::get('get-active-voucher', [AdminController::class,'getActiveVoucher']);
            Route::post('schedule-merchant-for-payment', [AdminController::class,'addToRemittance']);
            Route::get('/get-payment-schedule-list/{id}', [AdminController::class,'getRemittanceByVoucherCode']);
            Route::get('get-total-revenue-n-remittance', [AdminController::class,'getTotalRevenue']);


        });
    });

    Route::prefix('/activities')->group( function() {
        Route::middleware('auth:api')->group( function () {
            Route::get('/logs', [ActivityLogController::class, 'index'])->name('activity.logs');
        });
    });
});


 // MERCHANT EXTERNAL API
Route::prefix('v1/leverchain')->group(function() {

    Route::middleware('authorizationValidator')->group(function () {
        Route::post('transaction/initialize', [ExternalMerchant::class, 'initialize']);
    });
    Route::get('transaction/verify-request/{access_code}', [ExternalCheckout::class, 'verifyRequest']);
    Route::post('transaction/save-details', [ExternalCheckout::class, 'saveDetails']);
    Route::post('transaction/pay-with-transfer', [ExternalCheckout::class, 'payWithTransfer']);
    Route::post('transaction/pay-with-card', [ExternalCheckout::class, 'payWithCard']);
    Route::post('transaction/verify-card-otp', [ExternalCheckout::class, 'verifyCardOTP']);
});

//brails-kyc
Route::prefix('v1/brails-kyc')->group( function(){
    Route::middleware('auth:api')->group(function () {
        Route::post('send-phone-verification-otp', [KycController::class, 'phoneNumberVerification']);
        Route::post('send-email-verification-otp', [KycController::class, 'emailNumberVerification']);
        Route::post('verify-otp', [KycController::class, 'verifyOTP']);
        Route::get('check-kyc-status', [KycController::class, 'getKYCStatus']);
        
    });
});

Route::get('/levey/docs/{token}', [SwaggerUiController::class, 'index'])->where('token', '762815492636284');
