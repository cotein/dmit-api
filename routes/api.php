<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\ArbaController;
use App\Http\Controllers\Api\BankController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\Api\AfipIvaController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ReceiptController;
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
use App\Http\Controllers\Api\DashBoardController;
use App\Http\Controllers\PasswordResetController;

Route::get('updates', [DashBoardController::class, 'updates']);
Route::post('register', [RegisterController::class, 'register']);
Route::post('register/check-cuit', [RegisterController::class, 'checkCuit']);
Route::post('login', [AuthController::class, 'login']);
Route::post('auth/google', [AuthController::class, 'googleLogin']);
Route::get('verify-email', [EmailVerificationController::class, 'verify_email'])->name('verification.verify'); // Make sure to keep this as your route name
Route::post('email/resend', [EmailVerificationController::class, 'resend'])->name('verification.resend');
Route::post('password/email', [AuthController::class, 'forgotPassword']);
Route::post('forgotPassword/reset/code', [PasswordResetController::class, 'passwordResetCode']);
Route::post('forgotPassword/resetPassword', [PasswordResetController::class, 'resetPassword']);

Route::middleware(['auth:api'])->group(function () {

    Route::prefix('api')->group(function () {

        Route::prefix('arba')->group(function () {
            Route::post('/alicuota_por_sujeto', [ArbaController::class, 'alicuota_por_sujeto']);
        });

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
        Route::post('company/uploadLogo', [CompanyController::class, 'logo']);

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
            'bank' => BankController::class,
            'receipt' => ReceiptController::class,
        ]);
    });
});
