<?php

namespace App\Src\Services\MercadoPago;

use App\Models\MercadoPagoAccount;
use App\Models\MercadoPagoCharge;
use App\Src\Scopes\CompanyScope;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Cobro con QR dinámico en el mostrador.
 *
 * Mercado Pago necesita una sucursal y una caja (store + POS) para poder emitir
 * el QR: se crean la primera vez y quedan guardadas en la cuenta de la empresa.
 * El QR se genera por cobro y se muestra en pantalla; el estado del pago llega
 * por el mismo webhook que los links, porque la referencia externa es la misma.
 *
 * OJO: los endpoints de instore son los de la API de MP y todavía NO se probaron
 * contra el entorno real (falta una cuenta conectada). El primer uso con
 * credenciales tiene que verificarse llamada por llamada.
 */
class MercadoPagoPosService
{
    public function __construct(
        private MercadoPagoClient $client,
        private MercadoPagoOAuthService $oauth,
    ) {
    }

    /**
     * Crea la orden de QR de un cobro y devuelve lo que necesita la pantalla.
     *
     * VERIFICADO CONTRA LA API REAL (cuenta de prueba, oct 2026):
     *  - la ruta legacy `instore/orders/qr/.../orders/qr` YA NO EXISTE (404/405);
     *    el flujo vigente es `POST /v1/orders` con type=qr;
     *  - los importes van como STRING ("1500"), no como número: MP rechaza el
     *    número con "expected string, but got number";
     *  - la respuesta NO trae imagen del QR: el QR que se le muestra al cliente es
     *    el de la CAJA (`GET /pos` → `qr.image`), y la orden queda asociada a esa caja.
     */
    public function createQr(MercadoPagoCharge $charge): array
    {
        $account = MercadoPagoAccount::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $charge->company_id)
            ->first();

        if (! $account || ! $account->isConnected()) {
            throw new RuntimeException('La empresa no tiene una cuenta de Mercado Pago conectada.');
        }

        $client = $this->client->withToken($this->oauth->freshToken($account));

        $this->ensureStoreAndPos($client, $account);

        $importe = number_format((float) $charge->amount, 2, '.', '');

        $orden = $client->post('v1/orders', [
            'type' => 'qr',
            // Nuestra referencia: vuelve en el webhook y permite reconciliar.
            'external_reference' => $charge->external_reference,
            'description' => $charge->title ?: 'Cobro en mostrador',
            'total_amount' => $importe,
            'config' => [
                'qr' => ['external_pos_id' => (string) $account->pos_external_id],
            ],
            'transactions' => [
                'payments' => [['amount' => $importe]],
            ],
        ], 'qr-' . $charge->id);

        $charge->update([
            'channel' => MercadoPagoCharge::CHANNEL_POS_QR,
            'mp_order_id' => $orden['id'] ?? null,
            'status' => $this->mapOrderState($orden['status'] ?? null),
        ]);

        Log::info('Mercado Pago: orden de QR creada para el cobro ' . $charge->id, ['order_id' => $orden['id'] ?? null]);

        return [
            'order_id' => $orden['id'] ?? null,
            'payment_id' => $orden['transactions']['payments'][0]['id'] ?? null,
            'qr_image' => $this->posQrImage($client, (string) $account->pos_external_id),
            'expiration' => $orden['expiration_time'] ?? null,
        ];
    }

    /**
     * Cancela la orden de QR en Mercado Pago.
     *
     * Verificado contra la API real: `POST /v1/orders/{id}/cancel` responde 200 y
     * deja la orden en `canceled`; necesita la cabecera de idempotencia como el alta.
     */
    public function cancelOrder(MercadoPagoCharge $charge): array
    {
        $account = MercadoPagoAccount::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $charge->company_id)
            ->first();

        if (! $account || ! $account->isConnected()) {
            throw new RuntimeException('La empresa no tiene una cuenta de Mercado Pago conectada.');
        }

        $orden = $this->client
            ->withToken($this->oauth->freshToken($account))
            ->post('v1/orders/' . $charge->mp_order_id . '/cancel', [], 'cancel-order-' . $charge->id);

        $charge->update([
            'status' => $this->mapOrderState($orden['status'] ?? null),
            'status_detail' => $orden['status_detail'] ?? null,
        ]);

        return $orden;
    }

    /**
     * Imagen del QR de la caja: es lo que se le muestra al cliente para que escanee.
     */
    private function posQrImage(MercadoPagoClient $client, string $externalPosId): ?string
    {
        foreach ($client->get('pos')['results'] ?? [] as $pos) {
            if (($pos['external_id'] ?? '') === $externalPosId) {
                return $pos['qr']['image'] ?? null;
            }
        }

        return null;
    }

    /**
     * Estado de la orden de QR (Orders API) y, si ya hay pago, su id.
     *
     * El que imputa sigue siendo la conciliación de siempre: acá sólo se traduce.
     *
     * @return array{estado: string, payment_id: ?string}
     */
    public function orderStatus(MercadoPagoCharge $charge): array
    {
        $account = MercadoPagoAccount::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $charge->company_id)
            ->first();

        if (! $account || ! $account->isConnected()) {
            throw new RuntimeException('La empresa no tiene una cuenta de Mercado Pago conectada.');
        }

        $orden = $this->client->withToken($this->oauth->freshToken($account))->get('v1/orders/' . $charge->mp_order_id);

        $estado = $this->mapOrderState($orden['status'] ?? null);

        $charge->update([
            'status' => $estado,
            'status_detail' => $orden['status_detail'] ?? null,
        ]);

        // El id que trae la orden en `transactions.payments[]` NO es un id de
        // /v1/payments (probado contra la API real: buscarlo ahí da 404), así que
        // el pago se resuelve por nuestra referencia, que sí es la misma que usa
        // la conciliación de links.
        return ['estado' => $estado, 'payment_id' => null];
    }

    /**
     * Pagos de la orden ya cobrada, buscados por nuestra referencia externa.
     * Es el camino que también usa la conciliación de links, así que un cobro con
     * QR se imputa exactamente igual que uno con link.
     *
     * @return array<int, array>
     */
    public function paymentsFor(MercadoPagoCharge $charge): array
    {
        $account = MercadoPagoAccount::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $charge->company_id)
            ->first();

        if (! $account || ! $account->isConnected()) {
            throw new RuntimeException('La empresa no tiene una cuenta de Mercado Pago conectada.');
        }

        $respuesta = $this->client
            ->withToken($this->oauth->freshToken($account))
            ->get('v1/payments/search', ['external_reference' => $charge->external_reference]);

        return $respuesta['results'] ?? [];
    }

    /**
     * Estados de la ORDER (Orders API) traducidos a los del cobro. Es una función
     * pura a propósito: es lo único de este flujo que se puede probar sin cuenta.
     */
    public function mapOrderState(?string $estado): string
    {
        return match ($estado) {
            'processed', 'completed', 'approved' => MercadoPagoCharge::STATUS_APPROVED,
            'action_required', 'processing', 'in_process' => MercadoPagoCharge::STATUS_IN_PROCESS,
            'canceled', 'cancelled' => MercadoPagoCharge::STATUS_CANCELLED,
            'expired' => MercadoPagoCharge::STATUS_EXPIRED,
            'failed', 'rejected' => MercadoPagoCharge::STATUS_REJECTED,
            'created' => MercadoPagoCharge::STATUS_PENDING,
            default => MercadoPagoCharge::STATUS_PENDING,
        };
    }

    /**
     * Deja la cuenta con una caja (POS) usable, en este orden:
     *
     *  1. si ya la tenemos guardada, listo;
     *  2. si la cuenta YA tiene una caja en Mercado Pago, se reusa (es el camino
     *     más común y evita depender de la sucursal);
     *  3. si no hay ninguna, se crea: la caja EXIGE sucursal ("Point of sale must
     *     have store_id") y la sucursal EXIGE una ubicación con ciudad válida del
     *     catálogo de Mercado Pago. Si MP la rechaza, se propaga SU mensaje: es lo
     *     único que le dice al usuario qué corregir (o que cree la sucursal desde
     *     el panel de MP).
     *
     * Verificado contra la API real: /pos, /users/{id}/stores y /stores/search
     * existen; los external_id tienen que ser ALFANUMÉRICOS (sin guiones).
     */
    private function ensureStoreAndPos(MercadoPagoClient $client, MercadoPagoAccount $account): void
    {
        if (empty($account->mp_user_id)) {
            throw new RuntimeException('La cuenta de Mercado Pago no tiene usuario asociado: reconectala.');
        }

        if (! empty($account->pos_external_id)) {
            return;
        }

        $existente = $this->firstPos($client);

        if ($existente !== null) {
            $account->update(['pos_external_id' => $existente['external_id'] ?? $existente['id']]);
            Log::info('Mercado Pago: se reusa la caja existente de la cuenta', ['company_id' => $account->company_id]);

            return;
        }

        $storeId = $account->store_external_id ?: $this->ensureStore($client, $account);

        $pos = $client->post('pos', [
            'name' => 'Mostrador',
            'external_id' => 'DMITPOS' . $account->company_id,
            'fixed_amount' => false,
            'store_id' => $storeId,
        ]);

        $account->update(['pos_external_id' => $pos['external_id'] ?? ($pos['id'] ?? null)]);
    }

    /** Primera caja de la cuenta, si tiene alguna. */
    private function firstPos(MercadoPagoClient $client): ?array
    {
        try {
            $respuesta = $client->get('pos');

            return $respuesta['results'][0] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Sucursal existente o creación con la dirección de la empresa. */
    private function ensureStore(MercadoPagoClient $client, MercadoPagoAccount $account): string
    {
        $existente = $this->firstStore($client, $account->mp_user_id);

        if ($existente !== null) {
            $storeId = $existente['id'] ?? null;
            $account->update(['store_external_id' => $storeId]);

            return (string) $storeId;
        }

        try {
            $store = $client->post('users/' . $account->mp_user_id . '/stores', [
                'name' => 'DMIT',
                // Alfanumérico: Mercado Pago rechaza los guiones.
                'external_id' => 'DMITSTORE' . $account->company_id,
                'location' => $this->locationFor($account->company_id),
            ]);
        } catch (MercadoPagoException $e) {
            // El mensaje de MP es lo único que explica qué falta (ej. "location.city_name
            // was invalid"): se le muestra al usuario y se sugiere la salida por panel.
            throw new RuntimeException(
                'No se pudo crear la sucursal en Mercado Pago para poder mostrar el QR: ' . $e->getMessage()
                . ' — completá la dirección de la empresa (la ciudad tiene que existir en el catálogo de Mercado Pago) '
                . 'o creá la sucursal y la caja desde el panel de Mercado Pago.'
            );
        }

        $storeId = (string) ($store['id'] ?? '');
        $account->update(['store_external_id' => $storeId]);

        return $storeId;
    }

    private function firstStore(MercadoPagoClient $client, string $mpUserId): ?array
    {
        try {
            $respuesta = $client->get('users/' . $mpUserId . '/stores/search');

            return $respuesta['results'][0] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Ubicación de la sucursal a partir de la dirección de la empresa. Si falta
     * algo, se manda igual y MP contesta qué está mal: inventar coordenadas sería
     * peor que fallar con el motivo real.
     */
    private function locationFor(int $companyId): array
    {
        $company = \App\Models\Company::withoutGlobalScope(CompanyScope::class)->find($companyId);
        $address = $company?->address;

        return array_filter([
            'street_name' => $address->street ?? null,
            'street_number' => isset($address->number) ? (string) $address->number : null,
            'city_name' => $address->city ?? null,
            'zip_code' => $address->cp ?? null,
        ], fn ($valor) => $valor !== null && $valor !== '');
    }
}
