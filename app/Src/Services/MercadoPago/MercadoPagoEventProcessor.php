<?php

namespace App\Src\Services\MercadoPago;

use App\Models\MercadoPagoAccount;
use App\Models\MercadoPagoCharge;
use App\Models\MercadoPagoWebhookEvent;
use App\Src\Scopes\CompanyScope;
use Illuminate\Support\Facades\Log;

/**
 * Procesa una notificación de Mercado Pago ya validada y guardada.
 *
 * No confía en el contenido del webhook: usa el id del recurso para PEDIRLE el
 * pago a la API de Mercado Pago y concilia con eso. Un webhook sólo dice "algo
 * cambió"; el estado real se lee de la fuente.
 *
 * Vive en un servicio (y no en app/Jobs) porque ese directorio está owned by
 * root en este repo. Se despacha por cola vía closure desde el webhook, así el
 * día que haya worker corriendo pasa a segundo plano sin cambiar nada acá.
 */
class MercadoPagoEventProcessor
{
    public function __construct(
        private MercadoPagoClient $client,
        private MercadoPagoOAuthService $oauth,
        private MercadoPagoSettlementService $settlement,
        private MercadoPagoSubscriptionService $subscriptions,
        private MercadoPagoPointService $point,
        private MercadoPagoPosService $pos,
    ) {
    }

    public function process(int $eventId): void
    {
        $evento = MercadoPagoWebhookEvent::find($eventId);

        if (! $evento || $evento->status === MercadoPagoWebhookEvent::STATUS_PROCESSED) {
            return;
        }

        try {
            // Las suscripciones se autentican con las credenciales de DMIT, no
            // con el OAuth de una empresa: van por su propio camino.
            if (in_array($evento->topic, ['preapproval', 'subscription_preapproval'], true)) {
                $this->processSubscription($evento);

                return;
            }

            // Mercado Pago manda tópicos que no usamos. Se descartan ANTES de
            // resolver un token: si no, quedan como 'failed' y la conciliación
            // los reintenta para siempre sin que haya nada que hacer.
            if (! in_array($evento->topic, ['payment', 'merchant_order', 'point_integration_wh', 'point_integration_ipn', 'order'], true)) {
                $evento->markProcessed(MercadoPagoWebhookEvent::STATUS_IGNORED);

                return;
            }

            // Mostrador: la terminal Point avisa con sus tópicos y el QR del
            // mostrador con `order` (Orders API). Los dos terminan en el mismo
            // cobro, así que se resuelven por el canal del cobro.
            if (str_starts_with((string) $evento->topic, 'point_integration') || $evento->topic === 'order') {
                $this->processTerminalEvent($evento);

                return;
            }

            $token = $this->tokenFor($evento);

            if ($token === null) {
                $evento->markFailed('No se pudo resolver un access token para el evento.');

                return;
            }

            $recurso = $this->fetchResource($this->client->withToken($token), $evento);

            if ($recurso === null) {
                $evento->markProcessed(MercadoPagoWebhookEvent::STATUS_IGNORED);

                return;
            }

            $payment = $this->settlement->applyPayment($recurso);

            if ($payment === null) {
                // Pago de MP que no corresponde a ningún cobro nuestro.
                $evento->markProcessed(MercadoPagoWebhookEvent::STATUS_IGNORED);

                return;
            }

            $evento->update(['company_id' => $payment->company_id]);
            $evento->markProcessed();
        } catch (\Throwable $e) {
            $evento->markFailed($e->getMessage());

            Log::error('Mercado Pago: falló el procesamiento del webhook', [
                'event_id' => $eventId,
                'error' => $e->getMessage(),
            ]);

            // No se re-lanza: con QUEUE_CONNECTION=sync relanzar devuelve 500 a MP
            // y el reintento traería el mismo error. La conciliación por comando
            // levanta estos eventos en estado 'failed'.
        }
    }

    /**
     * Suscripción de DMIT: se pide el preapproval a Mercado Pago y se aplica su
     * estado. Usa las credenciales de plataforma, no el OAuth de una empresa.
     */
    private function processSubscription(MercadoPagoWebhookEvent $evento): void
    {
        $id = $evento->resource_id;

        if (empty($id)) {
            $evento->markFailed('La notificación de suscripción no trae el id del recurso.');

            return;
        }

        $token = config('mercadopago.platform_access_token');

        if (empty($token)) {
            $evento->markFailed('Falta MERCADOPAGO_PLATFORM_ACCESS_TOKEN.');

            return;
        }

        $preapproval = $this->client->withToken($token)->get('preapproval/' . $id);

        // Se busca por el id del acuerdo; si todavía no está guardado (por ejemplo
        // una suscripción creada a mano en el panel), se cae a nuestra referencia.
        $subscription = $this->subscriptions->findByPreapprovalId((string) $id);

        if (! $subscription) {
            $referencia = $preapproval['external_reference'] ?? null;
            $subscriptionId = $referencia && str_starts_with($referencia, 'DMIT-SUB-')
                ? (int) substr($referencia, strlen('DMIT-SUB-'))
                : null;

            $subscription = $subscriptionId
                ? \App\Models\Subscription::withoutGlobalScope(\App\Src\Scopes\CompanyScope::class)->find($subscriptionId)
                : null;
        }

        if (! $subscription) {
            Log::warning('Mercado Pago: suscripción sin fila local', ['preapproval_id' => $id]);
            $evento->markProcessed(MercadoPagoWebhookEvent::STATUS_IGNORED);

            return;
        }

        $this->subscriptions->applyPreapproval($subscription, $preapproval);

        $evento->update(['company_id' => $subscription->company_id]);
        $evento->markProcessed();
    }

    /**
     * Notificación de un cobro de mostrador (QR o terminal).
     *
     * El id que viene puede ser el de la orden/intent (lo que guardamos en
     * `mp_order_id`) o el del pago, así que se prueban los dos: primero el
     * nuestro —no es ambiguo— y si no hay cobro con ese id, se trata como pago.
     */
    private function processTerminalEvent(MercadoPagoWebhookEvent $evento): void
    {
        $id = $evento->resource_id;

        if (empty($id)) {
            $evento->markFailed('La notificación de la terminal no trae el id del recurso.');

            return;
        }

        $charge = MercadoPagoCharge::withoutGlobalScope(CompanyScope::class)
            ->where('mp_order_id', $id)
            ->first();

        if ($charge) {
            $this->settleTerminalCharge($evento, $charge);

            return;
        }

        $token = $this->tokenFor($evento);

        if ($token === null) {
            $evento->markFailed('No se pudo resolver un access token para el evento.');

            return;
        }

        $payment = $this->settlement->applyPayment($this->client->withToken($token)->get('v1/payments/' . $id));

        if ($payment === null) {
            $evento->markProcessed(MercadoPagoWebhookEvent::STATUS_IGNORED);

            return;
        }

        $evento->update(['company_id' => $payment->company_id]);
        $evento->markProcessed();
    }

    /**
     * Cobro de mostrador: se lee la orden (QR) o el intent (terminal), se saca el
     * pago y se concilia con el MISMO camino que un link. Acá no se mueve plata
     * por cuenta propia.
     */
    private function settleTerminalCharge(MercadoPagoWebhookEvent $evento, MercadoPagoCharge $charge): void
    {
        $account = MercadoPagoAccount::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $charge->company_id)
            ->first();

        if (! $account || ! $account->isConnected()) {
            $evento->markFailed('La empresa del cobro no tiene una cuenta de Mercado Pago conectada.');

            return;
        }

        // El QR del mostrador usa la Orders API; la terminal Point, sus intents.
        if ($charge->channel === MercadoPagoCharge::CHANNEL_POS_QR) {
            $this->settleQrCharge($evento, $charge);

            return;
        }

        $estado = $this->point->status($charge);

        if ($estado['payment_id'] === null) {
            // La terminal todavía no cobró: el cobro queda en proceso y no se imputa nada.
            $evento->update(['company_id' => $charge->company_id]);
            $evento->markProcessed();

            return;
        }

        $payment = $this->point->fetchPayment($account, $estado['payment_id']);
        $aplicado = $payment ? $this->settlement->applyPayment($payment) : null;

        if ($aplicado === null) {
            $evento->markFailed('El pago de la terminal no se pudo conciliar.');

            return;
        }

        $evento->update(['company_id' => $aplicado->company_id]);
        $evento->markProcessed();
    }

    /**
     * Cobro con QR del mostrador (Orders API).
     *
     * Verificado contra la API real: la orden se lee con `GET /v1/orders/{id}` y su
     * `transactions.payments[].id` NO sirve como id de pago. Mientras la orden no
     * esté paga no hay nada que imputar (y NO es un error: antes se marcaba fallido
     * y la conciliación lo reintentaba para siempre). Cuando está paga, el pago se
     * busca por nuestra referencia, igual que en los links.
     */
    private function settleQrCharge(MercadoPagoWebhookEvent $evento, MercadoPagoCharge $charge): void
    {
        $orden = $this->pos->orderStatus($charge);

        if ($orden['estado'] !== MercadoPagoCharge::STATUS_APPROVED) {
            // Todavía no pagó: el estado ya quedó actualizado, no hay nada más que hacer.
            $evento->update(['company_id' => $charge->company_id]);
            $evento->markProcessed();

            return;
        }

        $aplicado = null;

        foreach ($this->pos->paymentsFor($charge) as $pago) {
            $aplicado = $this->settlement->applyPayment($pago) ?? $aplicado;
        }

        if ($aplicado === null) {
            $evento->markFailed('La orden figura paga pero no se encontró el pago asociado.');

            return;
        }

        $evento->update(['company_id' => $aplicado->company_id]);
        $evento->markProcessed();
    }

    private function fetchResource(MercadoPagoClient $client, MercadoPagoWebhookEvent $evento): ?array
    {
        $id = $evento->resource_id;

        if (empty($id)) {
            return null;
        }

        return match ($evento->topic) {
            'payment' => $client->get('v1/payments/' . $id),
            // merchant_order no trae el pago: se resuelve por su payment id.
            'merchant_order' => $this->paymentFromMerchantOrder($client, $id),
            default => null,
        };
    }

    private function paymentFromMerchantOrder(MercadoPagoClient $client, string $orderId): ?array
    {
        $orden = $client->get('merchant_orders/' . $orderId);
        $paymentId = $orden['payments'][0]['id'] ?? null;

        if (empty($paymentId)) {
            return null;
        }

        return $client->get('v1/payments/' . $paymentId);
    }

    /**
     * Token para consultar el recurso. Primero el de la empresa dueña del cobro
     * (se deduce del payload); si no se sabe cuál es, el de cualquier cuenta
     * conectada: el webhook de MP no dice de quién es el pago, sólo el id.
     */
    private function tokenFor(MercadoPagoWebhookEvent $evento): ?string
    {
        $payload = $evento->payload ?? [];
        $companyId = $payload['metadata']['company_id'] ?? null;

        if ($companyId) {
            $account = MercadoPagoAccount::withoutGlobalScope(CompanyScope::class)
                ->where('company_id', $companyId)
                ->first();

            if ($account && $account->isConnected()) {
                try {
                    return $this->oauth->freshToken($account);
                } catch (\Throwable $e) {
                    Log::warning('Mercado Pago: no se pudo renovar el token de la empresa ' . $companyId, ['error' => $e->getMessage()]);
                }
            }
        }

        $account = MercadoPagoAccount::withoutGlobalScope(CompanyScope::class)
            ->where('status', MercadoPagoAccount::STATUS_CONNECTED)
            ->first();

        if (! $account) {
            return null;
        }

        try {
            return $this->oauth->freshToken($account);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
