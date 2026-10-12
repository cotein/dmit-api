<?php

namespace App\Src\Services\MercadoPago;

use App\Models\MercadoPagoAccount;
use App\Models\MercadoPagoCharge;
use App\Src\Scopes\CompanyScope;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Cobro con la terminal Mercado Pago Point (la maquinita del mostrador).
 *
 * Flujo: se listan las terminales de la cuenta, se manda el importe a una y el
 * cliente pasa la tarjeta. Cuando la terminal termina, Mercado Pago devuelve el
 * id del PAGO: con eso se llama a la conciliación de siempre, así que un cobro
 * con Point se imputa igual que uno con link o con QR (no hay un segundo camino
 * de dinero que mantener).
 *
 * OJO: los endpoints apuntan a la API de Point de Mercado Pago y todavía NO se
 * probaron contra el servicio real (no hay terminal ni credenciales acá). El
 * primer uso real tiene que verificarse llamada por llamada.
 */
class MercadoPagoPointService
{
    public function __construct(
        private MercadoPagoClient $client,
        private MercadoPagoOAuthService $oauth,
    ) {
    }

    /**
     * Estados que devuelve un payment intent, traducidos a los del cobro.
     * Es una función pura a propósito: es lo único de esta integración que se
     * puede probar sin terminal.
     */
    public function mapIntentState(?string $estado): string
    {
        return match ($estado) {
            'FINISHED' => MercadoPagoCharge::STATUS_APPROVED,
            'PROCESSING', 'ON_TERMINAL' => MercadoPagoCharge::STATUS_IN_PROCESS,
            'CANCELED' => MercadoPagoCharge::STATUS_CANCELLED,
            'ERROR' => MercadoPagoCharge::STATUS_REJECTED,
            'OPEN' => MercadoPagoCharge::STATUS_PENDING,
            default => MercadoPagoCharge::STATUS_PENDING,
        };
    }

    /** Terminales de la cuenta de la empresa. */
    public function devices(int $companyId): array
    {
        $account = $this->accountOf($companyId);

        $respuesta = $this->clientFor($account)->get('point/integration-api/devices');

        return $respuesta['devices'] ?? [];
    }

    /**
     * Manda el cobro a una terminal.
     *
     * @param  array{installments?: int, type?: string}  $opciones
     */
    public function sendToTerminal(MercadoPagoCharge $charge, string $deviceId, array $opciones = []): array
    {
        $account = $this->accountOf($charge->company_id);

        $intent = $this->clientFor($account)->post(
            'point/integration-api/devices/' . $deviceId . '/payment-intents',
            [
                'amount' => (float) $charge->amount,
                'description' => $charge->title ?: 'Cobro en mostrador',
                'additional_info' => [
                    // Nuestra referencia viaja al terminal y vuelve en el webhook.
                    'external_reference' => $charge->external_reference,
                    'print_on_terminal' => true,
                ],
                'payment' => [
                    'type' => $opciones['type'] ?? 'credit_card',
                    'installments' => (int) ($opciones['installments'] ?? 1),
                ],
            ],
            'point-' . $charge->id,
        );

        $charge->update([
            'channel' => MercadoPagoCharge::CHANNEL_POINT,
            'mp_order_id' => $intent['id'] ?? null,
            'status' => $this->mapIntentState($intent['state'] ?? null),
            // Se guarda la terminal para poder cancelar después en el mismo aparato.
            'metadata' => array_merge($charge->metadata ?? [], ['point_device_id' => $deviceId]),
        ]);

        Log::info('Mercado Pago: cobro enviado a la terminal', [
            'charge_id' => $charge->id,
            'device_id' => $deviceId,
            'intent_id' => $intent['id'] ?? null,
        ]);

        return $intent;
    }

    /** Cancela un intent que quedó esperando en la terminal. */
    public function cancelOnTerminal(MercadoPagoCharge $charge, string $deviceId): array
    {
        $account = $this->accountOf($charge->company_id);

        $respuesta = $this->clientFor($account)->delete(
            'point/integration-api/devices/' . $deviceId . '/payment-intents/' . $charge->mp_order_id
        );

        $charge->update(['status' => MercadoPagoCharge::STATUS_CANCELLED, 'status_detail' => 'Cancelado en la terminal']);

        return $respuesta;
    }

    /**
     * Lee el intent y devuelve el id de PAGO si la terminal ya cobró.
     *
     * El que imputa es la conciliación de siempre: acá sólo se traduce.
     *
     * @return array{estado: string, payment_id: ?string}
     */
    public function status(MercadoPagoCharge $charge): array
    {
        $account = $this->accountOf($charge->company_id);

        $intent = $this->clientFor($account)->get('point/integration-api/payment-intents/' . $charge->mp_order_id);

        $estado = $this->mapIntentState($intent['state'] ?? null);

        $charge->update([
            'status' => $estado,
            'status_detail' => $intent['state'] ?? null,
        ]);

        return [
            'estado' => $estado,
            'payment_id' => isset($intent['payment']['id']) ? (string) $intent['payment']['id'] : null,
        ];
    }

    /** El pago que dejó la terminal, para pasárselo a la conciliación. */
    public function fetchPayment(MercadoPagoAccount $account, string $paymentId): ?array
    {
        try {
            return $this->clientFor($account)->get('v1/payments/' . $paymentId);
        } catch (\Throwable $e) {
            Log::warning('Mercado Pago: no se pudo leer el pago de la terminal ' . $paymentId, ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function clientFor(MercadoPagoAccount $account): MercadoPagoClient
    {
        return $this->client->withToken($this->oauth->freshToken($account));
    }

    private function accountOf(int $companyId): MercadoPagoAccount
    {
        $account = MercadoPagoAccount::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)
            ->first();

        if (! $account || ! $account->isConnected()) {
            throw new RuntimeException('La empresa no tiene una cuenta de Mercado Pago conectada.');
        }

        return $account;
    }
}
