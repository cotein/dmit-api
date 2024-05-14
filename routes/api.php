<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\Api\AfipIvaController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\VoucherController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\AfipStateController;
use App\Http\Controllers\Api\PriceListController;
use App\Http\Controllers\Api\AfipPadronController;
use App\Http\Controllers\Api\AfipVoucherController;
use App\Http\Controllers\Api\PaymentTypeController;
use App\Http\Controllers\Api\SaleInvoiceController;
use App\Http\Controllers\Api\SaleConditionController;
use App\Http\Controllers\Api\AfipInscriptionController;
use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Api\AfipFacturaElectronicaController;

Route::post('register', [RegisterController::class, 'register']);
Route::post('register/check-cuit', [RegisterController::class, 'checkCuit']);
Route::post('login', [AuthController::class, 'login']);
Route::get('email/verify/{id}', [EmailVerificationController::class, 'verify'])->name('verification.verify')->middleware('signed');; // Make sure to keep this as your route name
Route::post('email/resend', [EmailVerificationController::class, 'resend'])->name('verification.resend');

Route::middleware(['auth:api'])->group(function () {

    Route::prefix('api')->group(function () {

        Route::prefix('afip')->group(function () {
            Route::get('/inscriptions', [AfipInscriptionController::class, 'index']);
            Route::get('/states', [AfipStateController::class, 'index']);
            Route::get('/ivas', [AfipIvaController::class, 'index']);
            Route::post('/getCompanyDataByPadron', [AfipPadronController::class, 'getCompanyDataByPadron']);
            Route::get('/FECompUltimoAutorizado', [AfipFacturaElectronicaController::class, 'FECompUltimoAutorizado']);
            Route::get('/FEParamGetPtosVenta', [AfipFacturaElectronicaController::class, 'FEParamGetPtosVenta']);
            Route::post('/FECAESolicitar', [AfipFacturaElectronicaController::class, 'FECAESolicitar']);
        });

        Route::post('/uploadAvatar', [UserController::class, 'uploadAvatar']);
        Route::post('product/img', [ProductController::class, 'img']);

        Route::apiResources([
            'category' => CategoryController::class,
            'company' => CompanyController::class,
            'customer' => CustomerController::class,
            'invoice' => SaleInvoiceController::class,
            'payment-type' =>  PaymentTypeController::class,
            'price-list' => PriceListController::class,
            'product' => ProductController::class,
            'sale-condition' => SaleConditionController::class,
            'users' => UserController::class,
            'voucher' => AfipVoucherController::class,
        ]);
    });
});
