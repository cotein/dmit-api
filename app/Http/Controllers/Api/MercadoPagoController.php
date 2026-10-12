<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMercadoPagoChargeRequest;
use App\Models\MercadoPagoAccount;
use App\Models\MercadoPagoCharge;
use App\Src\Repositories\MercadoPagoChargeRepository;
use App\Src\Scopes\CompanyScope;
use App\Src\Services\MercadoPago\MercadoPagoChargeService;
use App\Src\Services\MercadoPago\MercadoPagoClient;
use App\Src\Services\MercadoPago\MercadoPagoOAuthService;
use App\Src\Services\MercadoPago\MercadoPagoPointService;
use App\Src\Services\MercadoPago\MercadoPagoPosService;
use App\Src\Services\MercadoPago\MercadoPagoReconciliationService;
use App\Src\Services\MercadoPago\MercadoPagoSettlementService;
use App\Src\Support\CurrentCompany;
use App\Transformers\MercadoPagoAccountTransformer;
use App\Transformers\MercadoPagoChargeTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * API del módulo de pagos de Mercado Pago para la app (todo autenticado).
 *
 * El webhook receptor NO vive acá: está en MercadoPagoWebhookController, porque
 * lo llama Mercado Pago y no puede llevar auth:api.
 */
class MercadoPagoController extends Controller
{
    public function __construct(
        private MercadoPagoChargeRepository $repository,
        private MercadoPagoChargeService $chargeService,
        private MercadoPagoOAuthService $oauth,
        private MercadoPagoSettlementService $settlement,
        private MercadoPagoPosService $posService,
        private MercadoPagoPointService $pointService,
        private MercadoPagoReconciliationService $reconciliation,
        private MercadoPagoClient $client,
        private CurrentCompany $currentCompany,
    ) {
    }

    /**
     * Conciliación a pedido, acotada a la empresa activa.
     *
     * Es la red de seguridad cuando el webhook no llegó. Existe además del
     * comando programado porque el servidor puede no tener cron: sin esto, un
     * cobro perdido se queda pendiente para siempre.
     */
    public function reconcile(): JsonResponse
    {
        $companyId = $this->currentCompany->id();

        if ($companyId === null) {
            return response()->json(['message' => 'No hay empresa activa.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $vencidos = $this->reconciliation->expireOverdue($companyId);
        $cobros = $this->reconciliation->reconcileCharges($companyId, (int) config('mercadopago.reconciliation_limit'));
        $suscripciones = $this->reconciliation->reconcileSubscriptions($companyId, 10);

        return response()->json([
            'data' => [
                'expirados' => $vencidos,
                'cobros_revisados' => $cobros['revisados'],
                'cobros_actualizados' => $cobros['actualizados'],
                'suscripciones_revisadas' => $suscripciones['revisadas'],
            ],
            'message' => $this->mensajeDeConciliacion($cobros['actualizados']),
        ]);
    }

    private function mensajeDeConciliacion(int $actualizados): string
    {
        if ($actualizados === 0) {
            return 'Sin novedades: el estado de Mercado Pago coincide con el de DMIT.';
        }

        return $actualizados === 1
            ? 'Se actualizó 1 cobro con lo que informó Mercado Pago.'
            : 'Se actualizaron ' . $actualizados . ' cobros con lo que informó Mercado Pago.';
    }

    /**
     * Estado de la conexión de la empresa activa (para la pantalla de ajustes).
     */
    public function account(): JsonResponse
    {
        $account = $this->accountOfActiveCompany();

        if (! $account) {
            return response()->json(['data' => null, 'is_connected' => false]);
        }

        return fractal($account, new MercadoPagoAccountTransformer())->respond();
    }

    /**
     * URL de autorización de Mercado Pago a la que hay que mandar al dueño.
     */
    public function connect(Request $request): JsonResponse
    {
        $companyId = $this->currentCompany->id();

        if ($companyId === null) {
            return response()->json(['message' => 'No hay empresa activa.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $resultado = $this->oauth->authorizationUrl($companyId, (int) $request->user()->id);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json(['url' => $resultado['url'], 'state' => $resultado['state']]);
    }

    /**
     * Desconecta la cuenta: se borran los tokens y la empresa deja de poder cobrar.
     */
    public function disconnect(): JsonResponse
    {
        $account = $this->accountOfActiveCompany();

        if (! $account) {
            return response()->json(['message' => 'La empresa no tiene una cuenta conectada.'], Response::HTTP_NOT_FOUND);
        }

        $account->update([
            'access_token' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
            'status' => MercadoPagoAccount::STATUS_REVOKED,
            'last_error' => null,
        ]);

        return response()->json(['message' => 'Cuenta de Mercado Pago desconectada.']);
    }

    public function charges(Request $request): JsonResponse
    {
        $cobros = $this->repository->getPaginated($request->only([
            'status', 'concept', 'customer_id', 'sale_invoice_id', 'from', 'to', 'solo_con_link',
        ]), (int) $request->input('per_page', 15));

        return fractal($cobros, new MercadoPagoChargeTransformer())
            ->parseIncludes($request->input('include', 'customer,saleInvoice'))
            ->respond();
    }

    public function storeCharge(StoreMercadoPagoChargeRequest $request): JsonResponse
    {
        try {
            $charge = $this->chargeService->createLink($request->validated(), (int) $request->user()->id);
        } catch (RuntimeException $e) {
            // Falta de cuenta conectada o importe inválido: es culpa del pedido, no del server.
            return response()->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable $e) {
            Log::error('Mercado Pago: no se pudo crear el cobro', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Mercado Pago rechazó la creación del cobro.',
                'detail' => $e->getMessage(),
            ], Response::HTTP_BAD_GATEWAY);
        }

        return fractal($charge, new MercadoPagoChargeTransformer())
            ->parseIncludes('customer')
            ->respond(Response::HTTP_CREATED);
    }

    public function showCharge(MercadoPagoCharge $charge): JsonResponse
    {
        return fractal($charge, new MercadoPagoChargeTransformer())
            ->parseIncludes(request('include', 'customer,saleInvoice,payments'))
            ->respond();
    }

    public function cancelCharge(MercadoPagoCharge $charge): JsonResponse
    {
        try {
            $charge = $this->chargeService->cancel($charge);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return fractal($charge, new MercadoPagoChargeTransformer())->respond();
    }

    /**
     * Revalida un cobro contra Mercado Pago en el momento ("chequear ahora").
     * Sirve para no esperar el webhook cuando el cliente dice que ya pagó.
     */
    public function refreshCharge(MercadoPagoCharge $charge): JsonResponse
    {
        $account = $this->accountOfActiveCompany();

        if (! $account || ! $charge->preference_id) {
            return response()->json(['message' => 'El cobro todavía no tiene un link de Mercado Pago.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $token = $this->oauth->freshToken($account);
            $pagos = $this->client->withToken($token)->get('v1/payments/search', [
                'external_reference' => $charge->external_reference,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_BAD_GATEWAY);
        }

        foreach ($pagos['results'] ?? [] as $pago) {
            $this->settlement->applyPayment($pago);
        }

        return fractal($charge->refresh(), new MercadoPagoChargeTransformer())
            ->parseIncludes('customer,payments')
            ->respond();
    }

    /**
     * Genera el QR dinámico de un cobro para el mostrador y devuelve el texto que
     * la pantalla tiene que dibujar como QR.
     */
    public function qrCharge(MercadoPagoCharge $charge): JsonResponse
    {
        try {
            $qr = $this->posService->createQr($charge);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable $e) {
            Log::error('Mercado Pago: no se pudo generar el QR', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Mercado Pago rechazó la generación del QR.',
                'detail' => $e->getMessage(),
            ], Response::HTTP_BAD_GATEWAY);
        }

        return response()->json([
            'data' => [
                'charge_id' => (int) $charge->id,
                'external_reference' => $charge->external_reference,
                'amount' => (float) $charge->amount,
                'in_store_order_id' => $qr['order_id'],
                // El QR que escanea el cliente es el de la caja (MP no devuelve uno por orden).
                'qr_image' => $qr['qr_image'],
                'qr_data' => null,
            ],
        ]);
    }

    /**
     * Terminales Point de la cuenta (para elegir a cuál mandar el cobro).
     */
    public function devices(): JsonResponse
    {
        $companyId = $this->currentCompany->id();

        if ($companyId === null) {
            return response()->json(['message' => 'No hay empresa activa.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $devices = $this->pointService->devices($companyId);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'No se pudieron leer las terminales de Mercado Pago.',
                'detail' => $e->getMessage(),
            ], Response::HTTP_BAD_GATEWAY);
        }

        return response()->json([
            'data' => collect($devices)->map(fn (array $device) => [
                'id' => $device['id'] ?? null,
                'name' => $device['pos_id'] ?? ($device['id'] ?? null),
                'operating_mode' => $device['operating_mode'] ?? null,
            ])->values(),
        ]);
    }

    /**
     * Manda el cobro a una terminal Point. El cliente pasa la tarjeta y el pago
     * entra por el mismo webhook que los links (la referencia es la misma).
     */
    public function pointCharge(Request $request, MercadoPagoCharge $charge): JsonResponse
    {
        $datos = $request->validate([
            'device_id' => ['required', 'string', 'max:64'],
            'installments' => ['nullable', 'integer', 'min:1', 'max:24'],
            'type' => ['nullable', 'in:credit_card,debit_card'],
        ]);

        if ($charge->isPaid()) {
            return response()->json(['message' => 'El cobro ya está aprobado.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $intent = $this->pointService->sendToTerminal($charge, $datos['device_id'], [
                'installments' => $datos['installments'] ?? 1,
                'type' => $datos['type'] ?? 'credit_card',
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable $e) {
            Log::error('Mercado Pago: no se pudo enviar el cobro a la terminal', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Mercado Pago rechazó el envío a la terminal.',
                'detail' => $e->getMessage(),
            ], Response::HTTP_BAD_GATEWAY);
        }

        $charge = $charge->refresh();

        $payload = fractal($charge, new MercadoPagoChargeTransformer())->parseIncludes('customer')->toArray();
        $payload['data']['point_intent_id'] = $intent['id'] ?? null;
        $payload['data']['point_state'] = $intent['state'] ?? null;

        return response()->json($payload, Response::HTTP_CREATED);
    }

    private function accountOfActiveCompany(): ?MercadoPagoAccount
    {
        $companyId = $this->currentCompany->id();

        if ($companyId === null) {
            return null;
        }

        return MercadoPagoAccount::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)
            ->first();
    }
}
