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
use App\Http\Controllers\Api\RemitoController;
use App\Http\Controllers\Api\AfipInscriptionController;
use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Api\AfipFacturaElectronicaController;
use App\Http\Controllers\Api\DashBoardController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\MercadoPagoController;
use App\Http\Controllers\Api\MercadoPagoWebhookController;
use App\Http\Controllers\Api\MercadoPagoCallbackController;
use App\Http\Controllers\Api\MercadoLibreController;
use App\Http\Controllers\Api\MercadoLibreWebhookController;
use App\Http\Controllers\Api\MercadoLibreCallbackController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\Api\PasswordCodeController;
use App\Http\Controllers\Api\TwoFactorController;
use App\Http\Controllers\Api\MailController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\PlanAdminController;
use App\Http\Middleware\EnsurePlanAdmin;

Route::get('updates', [DashBoardController::class, 'updates']);
Route::post('register', [RegisterController::class, 'register']);
Route::post('register/check-cuit', [RegisterController::class, 'checkCuit']);
Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('auth/google', [AuthController::class, 'googleLogin'])->middleware('throttle:10,1');

// Doble factor y "entrar con un código". Son públicas (todavía no hay sesión: son
// parte del ingreso) y llevan throttle: el código se puede intentar adivinar.
Route::post('auth/two-factor', [AuthController::class, 'twoFactorChallenge'])->middleware('throttle:10,1');
Route::post('auth/login-code/request', [AuthController::class, 'requestLoginCode'])->middleware('throttle:5,1');
Route::post('auth/login-code/verify', [AuthController::class, 'verifyLoginCode'])->middleware('throttle:10,1');
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

    // MercadoLibre · mismas dos excepciones y por los mismos motivos:
    //   - webhook: lo llama MercadoLibre y tiene que responder en menos de 500 ms,
    //   - callback: es el navegador volviendo del OAuth, con la empresa firmada en el `state`.
    Route::post('mercadolibre/webhook', MercadoLibreWebhookController::class);
    Route::get('mercadolibre/callback', MercadoLibreCallbackController::class);
});

Route::middleware(['auth:api'])->group(function () {

    Route::prefix('api')->group(function () {

        // Cuenta: doble factor. Van bajo /api (y no en la raíz como las de ingreso)
        // porque el frontend firma con el token sólo los pedidos que llevan "api"
        // en la URL (ver src/api/base-api.ts del facturador).
        // Envío de comprobantes por correo. Antes el frontend le pegaba directo al
        // microservicio de emails (que quedó sin dominio): ahora pasa por acá y sale
        // por el mailer de Laravel, como el resto de los mails.
        Route::post('mails/invoice', [MailController::class, 'sendInvoice']);

        Route::prefix('account')->group(function () {
            Route::get('two-factor', [TwoFactorController::class, 'state']);
            Route::post('two-factor/totp/setup', [TwoFactorController::class, 'setupTotp']);
            Route::post('two-factor/totp/confirm', [TwoFactorController::class, 'confirmTotp']);
            Route::post('two-factor/email', [TwoFactorController::class, 'enableEmail']);
            Route::delete('two-factor', [TwoFactorController::class, 'disable']);
            Route::post('two-factor/recovery-codes', [TwoFactorController::class, 'regenerateRecoveryCodes']);
        });

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

        // Remitos: el documento que ampara el traslado y la entrega de bienes
        // (RG 1415, Anexo V). No es un comprobante fiscal: se emite ANTES de
        // facturar y después se vincula con una o varias facturas.
        //
        // `pendientes` va declarada ANTES de `{remito}`, si no Laravel intenta
        // resolver "pendientes" como si fuera un id de remito.
        Route::prefix('remitos')->group(function () {
            Route::get('pendientes', [RemitoController::class, 'pendientes']);
            Route::get('de-factura/{saleInvoice}', [RemitoController::class, 'deFactura']);
            Route::post('vincular-factura', [RemitoController::class, 'vincularFactura']);
            Route::get('/', [RemitoController::class, 'index']);
            Route::post('/', [RemitoController::class, 'store']);
            Route::get('{remito}', [RemitoController::class, 'show']);
            Route::put('{remito}', [RemitoController::class, 'update']);
            Route::delete('{remito}', [RemitoController::class, 'destroy']);
            Route::get('{remito}/pdf', [RemitoController::class, 'pdf']);
            Route::post('{remito}/emitir', [RemitoController::class, 'emitir']);
            Route::post('{remito}/anular', [RemitoController::class, 'anular']);
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

        // MercadoLibre: publicaciones del vendedor, conciliación con el catálogo
        // local, precio/stock hacia MercadoLibre y ventas como pedidos.
        Route::prefix('mercadolibre')->group(function () {
            Route::get('account', [MercadoLibreController::class, 'account']);
            Route::get('connect', [MercadoLibreController::class, 'connect']);
            Route::delete('account', [MercadoLibreController::class, 'disconnect']);

            Route::get('items', [MercadoLibreController::class, 'items']);
            Route::get('items/unmatched', [MercadoLibreController::class, 'unmatched']);
            Route::post('items/{item}/link', [MercadoLibreController::class, 'link']);
            Route::post('items/{item}/unlink', [MercadoLibreController::class, 'unlink']);
            Route::post('items/{item}/ignore', [MercadoLibreController::class, 'ignore']);
            Route::post('items/{item}/import-as-product', [MercadoLibreController::class, 'importAsProduct']);
            Route::post('items/{item}/push', [MercadoLibreController::class, 'pushItem']);
            Route::post('items/{item}/status', [MercadoLibreController::class, 'changeStatus']);
            Route::post('rematch', [MercadoLibreController::class, 'rematch']);

            Route::post('sync', [MercadoLibreController::class, 'sync']);
            Route::post('push', [MercadoLibreController::class, 'push']);
            Route::post('orders/import', [MercadoLibreController::class, 'importOrders']);
            Route::post('products/{product}/publish', [MercadoLibreController::class, 'publish']);
            Route::get('logs', [MercadoLibreController::class, 'logs']);
        });

        // Suscripción de la empresa a un plan de DMIT (se cobra con las
        // credenciales de plataforma, no con el OAuth del comercio).
        Route::prefix('subscription')->group(function () {
            Route::get('plans', [SubscriptionController::class, 'plans']);
            Route::get('/', [SubscriptionController::class, 'current']);
            Route::post('/', [SubscriptionController::class, 'store']);
            Route::post('cancel', [SubscriptionController::class, 'cancel']);
        });

        // Plan del CUIT activo: qué rige, cuánto se usó y cómo se cambia. Es lo que
        // alimenta el contador de la pantalla; quien decide si se puede emitir es el
        // camino de emisión, con el contador real.
        Route::prefix('plan')->group(function () {
            Route::get('/', [PlanController::class, 'show']);
            Route::get('catalog', [PlanController::class, 'catalog']);
            Route::post('change', [PlanController::class, 'change']);
            Route::get('history', [PlanController::class, 'history']);
        });

        // Configuración INTERNA del corte de planes: interruptores, recálculo,
        // saneamiento y asignación de planes. No es pantalla de cliente: se protege
        // con el secreto de operación o con el permiso CREAR COMPAÑIA.
        Route::prefix('internal/plan')->middleware(EnsurePlanAdmin::class)->group(function () {
            Route::get('state', [PlanAdminController::class, 'state']);
            Route::put('switches', [PlanAdminController::class, 'switches']);
            Route::post('sanitize', [PlanAdminController::class, 'sanitize']);
            Route::post('recalculate', [PlanAdminController::class, 'recalculate']);
            Route::post('assign', [PlanAdminController::class, 'assign']);
            Route::post('backfill', [PlanAdminController::class, 'backfill']);
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
