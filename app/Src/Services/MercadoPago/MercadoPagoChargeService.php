<?php

namespace App\Src\Services\MercadoPago;

use App\Models\MercadoPagoAccount;
use App\Models\MercadoPagoCharge;
use App\Src\Scopes\CompanyScope;
use App\Src\Support\CurrentCompany;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Alta de cobros para los clientes de una empresa (Checkout Pro).
 *
 * El cobro se persiste ANTES de pedirle la preferencia a Mercado Pago: si MP
 * rechaza la llamada queda la fila con su error, y si el usuario reintenta no se
 * pierde el rastro de la intención. `external_reference` es nuestro id y es lo
 * que hace que un pago que llega por webhook se pueda reconciliar con su origen.
 */
class MercadoPagoChargeService
{
    public function __construct(
        private MercadoPagoClient $client,
        private MercadoPagoOAuthService $oauth,
        private CurrentCompany $currentCompany,
        private MercadoPagoPosService $pos,
        private MercadoPagoPointService $point,
    ) {
    }

    /**
     * @param  array{amount: float|int|string, concept: string, title?: string, description?: string, customer_id?: int, sale_invoice_id?: int, expires_in_days?: int, metadata?: array}  $data
     */
    public function createLink(array $data, ?int $userId = null): MercadoPagoCharge
    {
        $companyId = $this->currentCompany->id();

        if ($companyId === null) {
            throw new RuntimeException('No hay empresa activa para generar el cobro.');
        }

        $amount = round((float) $data['amount'], 2);

        if ($amount <= 0) {
            throw new RuntimeException('El importe del cobro tiene que ser mayor a cero.');
        }

        $account = $this->accountFor($companyId);

        $charge = (new MercadoPagoCharge)->forceFill([
            'company_id' => $companyId,
            'customer_id' => $data['customer_id'] ?? null,
            'sale_invoice_id' => $data['sale_invoice_id'] ?? null,
            'created_by' => $userId,
            'channel' => MercadoPagoCharge::CHANNEL_LINK,
            'concept' => $data['concept'] ?? MercadoPagoCharge::CONCEPT_MANUAL,
            'external_reference' => $this->uniqueReference(),
            'amount' => $amount,
            'currency' => 'ARS',
            'title' => $data['title'] ?? 'Pago',
            'description' => $data['description'] ?? null,
            'status' => MercadoPagoCharge::STATUS_PENDING,
            'expires_at' => ! empty($data['expires_in_days']) ? now()->addDays((int) $data['expires_in_days']) : null,
            'metadata' => $data['metadata'] ?? null,
        ]);
        $charge->save();

        try {
            $preferencia = $this->client
                ->withToken($this->oauth->freshToken($account))
                ->post('checkout/preferences', $this->preferencePayload($charge), 'charge-' . $charge->id);

            $charge->update([
                'preference_id' => $preferencia['id'] ?? null,
                'init_point' => $preferencia['init_point'] ?? null,
                'sandbox_init_point' => $preferencia['sandbox_init_point'] ?? null,
            ]);
        } catch (\Throwable $e) {
            // El cobro queda pendiente y sin link: se ve en pantalla y se puede reintentar.
            $charge->update([
                'status_detail' => 'No se pudo crear el link: ' . mb_substr($e->getMessage(), 0, 400),
            ]);

            throw $e;
        }

        return $charge->refresh();
    }

    /**
     * Anula un cobro pendiente.
     *
     * Una PREFERENCIA (link) no se puede cancelar por API, así que el link deja
     * de mostrarse y, si alguien lo paga igual, el webhook entra y se concilia.
     * En cambio una ORDEN de QR y un envío a terminal SÍ se cancelan: si no se
     * avisa, el cliente se encuentra con un cobro vivo en la maquinita o en el QR.
     *
     * Ese aviso es best-effort a propósito: si Mercado Pago no contesta, la
     * anulación local igual se hace (queda registrada) y el problema se ve en el
     * log, en vez de dejar al usuario sin poder anular.
     */
    public function cancel(MercadoPagoCharge $charge): MercadoPagoCharge
    {
        if ($charge->isPaid()) {
            throw new RuntimeException('El cobro ya está aprobado: para revertirlo hace falta una devolución.');
        }

        $esteAviso = $this->cancelarEnMercadoPago($charge);

        $charge->update([
            'status' => MercadoPagoCharge::STATUS_CANCELLED,
            'status_detail' => 'Anulado desde DMIT' . $esteAviso,
        ]);

        return $charge->refresh();
    }

    /** @return string sufijo para el detalle del estado (vacío si no hubo nada que avisar) */
    private function cancelarEnMercadoPago(MercadoPagoCharge $charge): string
    {
        if (empty($charge->mp_order_id)) {
            return '';
        }

        try {
            if ($charge->channel === MercadoPagoCharge::CHANNEL_POS_QR) {
                $this->pos->cancelOrder($charge);

                return ' y en Mercado Pago';
            }

            if ($charge->channel === MercadoPagoCharge::CHANNEL_POINT) {
                $deviceId = $charge->metadata['point_device_id'] ?? null;

                if ($deviceId === null) {
                    return '';
                }

                $this->point->cancelOnTerminal($charge, (string) $deviceId);

                return ' y en la terminal';
            }
        } catch (\Throwable $e) {
            Log::warning('Mercado Pago: el cobro se anuló acá pero MP no confirmó la anulación', [
                'charge_id' => $charge->id,
                'channel' => $charge->channel,
                'error' => $e->getMessage(),
            ]);
        }

        return '';
    }

    /**
     * Marca como vencidos los cobros que pasaron su fecha sin pagarse.
     *
     * Sin esto quedan "pendientes" para siempre: ensucian el listado y —peor—
     * la conciliación los sigue consultando contra Mercado Pago cada 10 minutos
     * aunque el link ya no acepte pagos.
     *
     * Usa withoutGlobalScope a propósito: corre desde un comando, donde no hay
     * usuario autenticado y el scope de empresa (fail-closed) no devolvería nada.
     *
     * @param  int|null  $companyId  null = todas las empresas (comando)
     * @return int cantidad de cobros marcados
     */
    public function expireOverdue(?int $companyId = null): int
    {
        $query = MercadoPagoCharge::withoutGlobalScope(CompanyScope::class)
            ->whereIn('status', [MercadoPagoCharge::STATUS_PENDING, MercadoPagoCharge::STATUS_IN_PROCESS])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now());

        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        return $query->update([
            'status' => MercadoPagoCharge::STATUS_EXPIRED,
            'status_detail' => 'Venció sin pagarse',
        ]);
    }

    /**
     * Cuenta conectada de la empresa. Sin cuenta no hay a quién cobrarle: el
     * error lo tiene que ver el usuario, no perderse en un log.
     */
    private function accountFor(int $companyId): MercadoPagoAccount
    {
        $account = MercadoPagoAccount::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)
            ->first();

        if (! $account || ! $account->isConnected()) {
            throw new RuntimeException('La empresa no tiene una cuenta de Mercado Pago conectada.');
        }

        return $account;
    }

    private function preferencePayload(MercadoPagoCharge $charge): array
    {
        $payload = [
            'items' => [[
                'id' => (string) $charge->id,
                'title' => $charge->title ?: 'Pago',
                'description' => $charge->description ?: '',
                'quantity' => 1,
                'currency_id' => $charge->currency,
                'unit_price' => (float) $charge->amount,
            ]],
            'external_reference' => $charge->external_reference,
            'notification_url' => config('mercadopago.webhook_url'),
            'metadata' => array_merge($charge->metadata ?? [], [
                'company_id' => $charge->company_id,
                'charge_id' => $charge->id,
                'concept' => $charge->concept,
            ]),
        ];

        $backUrl = config('mercadopago.back_url');

        if (! empty($backUrl)) {
            $payload['back_urls'] = [
                'success' => $backUrl,
                'pending' => $backUrl,
                'failure' => $backUrl,
            ];

            // auto_return sólo es válido con back_urls HTTPS: en local MP lo rechaza.
            if (Str::startsWith($backUrl, 'https://')) {
                $payload['auto_return'] = 'approved';
            }
        }

        if ($charge->expires_at) {
            $payload['expires'] = true;
            $payload['expiration_date_to'] = $charge->expires_at->toIso8601String();
        }

        return $payload;
    }

    /**
     * Referencia nuestra, única y opaca (no expone ids internos en la URL de MP).
     */
    private function uniqueReference(): string
    {
        do {
            $referencia = 'DMIT-' . strtoupper(Str::random(12));
        } while (MercadoPagoCharge::withoutGlobalScope(CompanyScope::class)->where('external_reference', $referencia)->exists());

        return $referencia;
    }
}
