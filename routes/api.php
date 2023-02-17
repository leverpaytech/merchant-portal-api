<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use \App\Http\Controllers\AuthController;
use \App\Http\Controllers\UserController;
use \App\Http\Controllers\ActivityLogController;


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
Route::post('/login',[AuthController::class, 'login'])->name('login');



Route::prefix('/merchants')->group( function() {
    Route::post('/sign-up', [UserController::class, 'create'])->name('merchant.sign-up');
    Route::get('/verify-email/{token}', [AuthController::class, 'verifyEmail'])->name('verify-email');

    Route::middleware('auth:api')->group( function () {
        Route::get('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/get/{id}', [UserController::class, 'get'])->name('merchant.get');
    });
});

Route::prefix('/admin')->group( function() {
    Route::middleware('auth:api')->group( function () {
        Route::get('/', [UserController::class, 'index'])->name('merchants.all');
    });
});

Route::prefix('/activities')->group( function() {
    Route::middleware('auth:api')->group( function () {
        Route::get('/logs', [ActivityLogController::class, 'index'])->name('activity.logs');
    });
});
