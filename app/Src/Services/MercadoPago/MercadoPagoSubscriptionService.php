<?php

namespace App\Src\Services\MercadoPago;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Suscripciones de DMIT: lo que cada comercio le paga a DMIT por usar el sistema.
 *
 * OJO con las credenciales: acá se usa SIEMPRE el token de plataforma (la cuenta
 * de DMIT), nunca el OAuth de la empresa. Al revés que los cobros a clientes del
 * comercio, donde la plata va a la cuenta del comercio. Mezclarlos mandaría la
 * suscripción a la cuenta equivocada.
 */
class MercadoPagoSubscriptionService
{
    public function __construct(private MercadoPagoClient $client)
    {
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, SubscriptionPlan> */
    public function activePlans()
    {
        return SubscriptionPlan::where('active', true)->orderBy('amount')->get();
    }

    /**
     * Da de alta la suscripción de una empresa y devuelve el link para que el
     * dueño autorice el débito en Mercado Pago.
     */
    public function subscribe(int $companyId, int $planId, ?string $payerEmail, ?int $payerUserId = null): Subscription
    {
        $plan = SubscriptionPlan::find($planId);

        if (! $plan || ! $plan->active) {
            throw new RuntimeException('El plan elegido no existe o no está disponible.');
        }

        $existing = Subscription::where('company_id', $companyId)
            ->whereIn('status', [Subscription::STATUS_PENDING, Subscription::STATUS_AUTHORIZED])
            ->first();

        if ($existing) {
            throw new RuntimeException('Esta empresa ya tiene una suscripción activa o pendiente.');
        }

        $subscription = Subscription::create([
            'company_id' => $companyId,
            'plan_id' => $plan->id,
            'payer_user_id' => $payerUserId,
            'payer_email' => $payerEmail,
            'status' => Subscription::STATUS_PENDING,
        ]);

        try {
            $preapproval = $this->client
                ->withToken($this->platformToken())
                ->post('preapproval', [
                    'reason' => 'Suscripción DMIT — ' . $plan->name,
                    // Nuestra referencia: es lo que vuelve en el webhook.
                    'external_reference' => 'DMIT-SUB-' . $subscription->id,
                    'payer_email' => $payerEmail,
                    'back_url' => config('mercadopago.back_url'),
                    'status' => 'pending',
                    'auto_recurring' => [
                        'frequency' => 1,
                        'frequency_type' => $plan->period === SubscriptionPlan::PERIOD_YEARLY ? 'years' : 'months',
                        'transaction_amount' => (float) $plan->amount,
                        'currency_id' => $plan->currency,
                    ],
                ], 'subscription-' . $subscription->id);

            $subscription->update([
                'mp_preapproval_id' => $preapproval['id'] ?? null,
                'status' => $this->mapStatus($preapproval['status'] ?? null),
                'started_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // La suscripción queda pendiente y sin link: se ve en pantalla y se reintenta.
            Log::warning('Mercado Pago: no se pudo crear el preapproval', ['error' => $e->getMessage()]);

            throw $e;
        }

        return $subscription->refresh();
    }

    /**
     * Lee el estado real del acuerdo en Mercado Pago y lo aplica localmente.
     * Es lo que usan el webhook y la conciliación.
     */
    public function sync(Subscription $subscription): Subscription
    {
        if (empty($subscription->mp_preapproval_id)) {
            return $subscription;
        }

        $preapproval = $this->client
            ->withToken($this->platformToken())
            ->get('preapproval/' . $subscription->mp_preapproval_id);

        $this->applyPreapproval($subscription, $preapproval);

        return $subscription->refresh();
    }

    /**
     * Aplica un preapproval (tal cual lo devuelve MP) sobre la suscripción.
     * Idempotente: lo pueden llamar el webhook y la conciliación a la vez.
     */
    public function applyPreapproval(Subscription $subscription, array $preapproval): void
    {
        $estado = $this->mapStatus($preapproval['status'] ?? null);
        $proximoPago = $preapproval['next_payment_date'] ?? null;

        $subscription->update([
            'status' => $estado,
            'payer_email' => $preapproval['payer_email'] ?? $subscription->payer_email,
            'next_payment_at' => $proximoPago ? \Carbon\Carbon::parse($proximoPago) : $subscription->next_payment_at,
            'last_payment_at' => $estado === Subscription::STATUS_AUTHORIZED ? now() : $subscription->last_payment_at,
            'cancelled_at' => $estado === Subscription::STATUS_CANCELLED ? now() : $subscription->cancelled_at,
        ]);
    }

    /**
     * Cancela el débito en Mercado Pago y localmente. Si MP falla, NO se marca
     * cancelada acá: el estado local tiene que reflejar lo que pasa en MP.
     */
    public function cancel(Subscription $subscription): Subscription
    {
        if (empty($subscription->mp_preapproval_id)) {
            throw new RuntimeException('La suscripción no tiene un acuerdo creado en Mercado Pago.');
        }

        $preapproval = $this->client
            ->withToken($this->platformToken())
            ->put('preapproval/' . $subscription->mp_preapproval_id, ['status' => 'cancelled']);

        $this->applyPreapproval($subscription, $preapproval);

        return $subscription->refresh();
    }

    /**
     * Busca la suscripción por el id de preapproval. La usa el webhook, que sólo
     * trae el id del recurso.
     */
    public function findByPreapprovalId(string $preapprovalId): ?Subscription
    {
        return Subscription::withoutGlobalScope(\App\Src\Scopes\CompanyScope::class)
            ->where('mp_preapproval_id', $preapprovalId)
            ->first();
    }

    private function platformToken(): string
    {
        $token = config('mercadopago.platform_access_token');

        if (empty($token)) {
            throw new RuntimeException(
                'Falta MERCADOPAGO_PLATFORM_ACCESS_TOKEN: sin las credenciales de DMIT no se pueden cobrar suscripciones.'
            );
        }

        return $token;
    }

    private function mapStatus(?string $mpStatus): string
    {
        return match ($mpStatus) {
            'authorized' => Subscription::STATUS_AUTHORIZED,
            'paused' => Subscription::STATUS_PAUSED,
            'cancelled' => Subscription::STATUS_CANCELLED,
            default => Subscription::STATUS_PENDING,
        };
    }
}
