<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\Api\AfipInscriptionController;
use App\Http\Controllers\Api\AfipPadronController;
use App\Http\Controllers\Api\AfipStateController;
use App\Http\Controllers\Api\EmailVerificationController;

Route::post('register', [RegisterController::class, 'register']);
Route::post('register/check-cuit', [RegisterController::class, 'checkCuit']);
Route::get('login', [AuthController::class, 'login']);
//Route::post('email/verification-notification', [EmailVerificationController::class, 'sendVerificationEmail']);
Route::get('email/verify/{id}', [EmailVerificationController::class, 'verify'])->name('verification.verify')->middleware('signed');; // Make sure to keep this as your route name
Route::post('email/resend', [EmailVerificationController::class, 'resend'])->name('verification.resend');

Route::prefix('afip')->group(function () {
    Route::get('/inscriptions', [AfipInscriptionController::class, 'index']);
    Route::get('/states', [AfipStateController::class, 'index']);
    Route::get('/getCompanyDataByPadron', [AfipPadronController::class, 'getCompanyDataByPadron']);
});

Route::middleware(['auth:api'])->group(function () {
    Route::apiResource('users', UserController::class);
});
