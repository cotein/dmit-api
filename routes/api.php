<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\Api\AfipInscriptionController;
use App\Http\Controllers\Api\AfipPadronController;
use App\Http\Controllers\Api\AfipStateController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\EmailVerificationController;

Route::post('register', [RegisterController::class, 'register']);
Route::post('register/check-cuit', [RegisterController::class, 'checkCuit']);
Route::post('login', [AuthController::class, 'login']);
Route::get('email/verify/{id}', [EmailVerificationController::class, 'verify'])->name('verification.verify')->middleware('signed');; // Make sure to keep this as your route name
Route::post('email/resend', [EmailVerificationController::class, 'resend'])->name('verification.resend');

//Route::middleware(['auth:api'])->group(function () {

Route::prefix('api')->group(function () {

    Route::prefix('afip')->group(function () {
        Route::get('/inscriptions', [AfipInscriptionController::class, 'index']);
        Route::get('/states', [AfipStateController::class, 'index']);
        Route::post('/getCompanyDataByPadron', [AfipPadronController::class, 'getCompanyDataByPadron']);
    });

    Route::apiResource('users', UserController::class);
    Route::apiResource('company', CompanyController::class);
});
//});
