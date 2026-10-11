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
use App\Http\Controllers\Api\ReceiptPaymentController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\AfipStateController;
use App\Http\Controllers\Api\PriceListController;
use App\Http\Controllers\Api\AfipPadronController;
use App\Http\Controllers\Api\AfipVoucherController;
use App\Http\Controllers\Api\PaymentTypeController;
use App\Http\Controllers\Api\SaleInvoiceController;
use App\Http\Controllers\Api\SaleConditionController;
use App\Http\Controllers\Api\CotController;
use App\Http\Controllers\Api\AfipInscriptionController;
use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Api\AfipFacturaElectronicaController;
use App\Http\Controllers\Api\DashBoardController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\MercadoPagoController;
use App\Http\Controllers\Api\MercadoPagoWebhookController;
use App\Http\Controllers\Api\MercadoPagoCallbackController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\Api\PasswordCodeController;

Route::get('updates', [DashBoardController::class, 'updates']);
Route::post('register', [RegisterController::class, 'register']);
Route::post('register/check-cuit', [RegisterController::class, 'checkCuit']);
Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('auth/google', [AuthController::class, 'googleLogin'])->middleware('throttle:10,1');
Route::get('verify-email', [EmailVerificationController::class, 'verify_email'])->name('verification.verify'); // Make sure to keep this as your route name
Route::post('email/resend', [EmailVerificationController::class, 'resend'])->name('verification.resend');

// Restablecer la contraseña: dos caminos que conviven a propósito.
//  - Código de 6 dígitos (nuevo): password/code/request + password/code/reset
//  - Enlace con token (el de siempre, para los mails ya enviados y quien lo prefiera)
// Todas son rutas PÚBLICAS (quien perdió la contraseña no tiene sesión), así que
// llevan throttle: sin eso se usan para inundar de mails a cualquiera.
// Se borró `password/email`: apuntaba a AuthController::forgotPassword, un método
// que no existe (cualquier llamada era un 500).
Route::post('password/code/request', [PasswordCodeController::class, 'request'])->middleware('throttle:5,1');
Route::post('password/code/reset', [PasswordCodeController::class, 'reset'])->middleware('throttle:10,1');
Route::post('forgotPassword/reset/code', [PasswordResetController::class, 'passwordResetCode'])->middleware('throttle:5,1');
Route::post('forgotPassword/resetPassword', [PasswordResetController::class, 'resetPassword'])->middleware('throttle:10,1');

Route::get('status', function () {
    return response()->json([
        'data' => [
            'message' => '¡API Conectada y Operativa desde el contenedor Docker!',
            'timestamp' => now()
        ]
    ]);
});

// Mercado Pago · rutas PÚBLICAS (sin auth:api, y no pueden tenerla):
//   - webhook: lo llama Mercado Pago. Se protege con la firma x-signature, no con sesión.
//   - callback: es el navegador del usuario volviendo del OAuth; la empresa y el
//     usuario viajan firmados en el `state`.
Route::prefix('api')->group(function () {
    Route::post('mercado-pago/webhook', MercadoPagoWebhookController::class);
    Route::get('mercado-pago/callback', MercadoPagoCallbackController::class);
});

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

        // COT · ARBA Buenos Aires. Entidad propia y previa al traslado.
        Route::prefix('arba/cot')->group(function () {
            Route::get('catalogos', [CotController::class, 'catalogos']);
            Route::get('/', [CotController::class, 'index']);
            Route::post('/', [CotController::class, 'store']);
            Route::get('{cot}', [CotController::class, 'show']);
            Route::put('{cot}', [CotController::class, 'update']);
            Route::post('{cot}/emitir', [CotController::class, 'emitir']);
            Route::get('{cot}/archivo', [CotController::class, 'archivo']);
            Route::post('{cot}/anular', [CotController::class, 'anular']);
        });

        // Pagos recibidos: una fila por pago (receipt_payments), no por recibo.
        Route::get('receipt-payments', [ReceiptPaymentController::class, 'index']);

        // Anulación de un recibo: se marca con motivo y fecha, y se revierte la
        // imputación sobre los comprobantes.
        Route::post('receipt/{receipt}/anular', [ReceiptController::class, 'anular']);

        // Cobros online con Mercado Pago (link a clientes del comercio).
        // El binding {charge} usa el global scope de empresa: un usuario no puede
        // tocar el cobro de otra empresa ni conociendo el id.
        Route::prefix('mercado-pago')->group(function () {
            Route::get('account', [MercadoPagoController::class, 'account']);
            Route::get('connect', [MercadoPagoController::class, 'connect']);
            Route::delete('account', [MercadoPagoController::class, 'disconnect']);
            Route::get('charges', [MercadoPagoController::class, 'charges']);
            Route::post('charges', [MercadoPagoController::class, 'storeCharge']);
            Route::get('charges/{charge}', [MercadoPagoController::class, 'showCharge']);
            Route::post('charges/{charge}/cancel', [MercadoPagoController::class, 'cancelCharge']);
            Route::post('charges/{charge}/refresh', [MercadoPagoController::class, 'refreshCharge']);
            // QR dinámico para cobrar en el mostrador.
            Route::post('charges/{charge}/qr', [MercadoPagoController::class, 'qrCharge']);
            // Terminales Point: listarlas y mandarles un cobro.
            Route::get('devices', [MercadoPagoController::class, 'devices']);
            Route::post('charges/{charge}/point', [MercadoPagoController::class, 'pointCharge']);
            // Conciliación a pedido (red de seguridad si el webhook no llegó).
            Route::post('reconcile', [MercadoPagoController::class, 'reconcile']);
        });

        // Suscripción de la empresa a un plan de DMIT (se cobra con las
        // credenciales de plataforma, no con el OAuth del comercio).
        Route::prefix('subscription')->group(function () {
            Route::get('plans', [SubscriptionController::class, 'plans']);
            Route::get('/', [SubscriptionController::class, 'current']);
            Route::post('/', [SubscriptionController::class, 'store']);
            Route::post('cancel', [SubscriptionController::class, 'cancel']);
        });

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
            'orders'=> OrderController::class,
            'payments' => PaymentController::class
        ]);
    });
});
