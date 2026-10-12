<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Src\Scopes\CompanyScope;
use App\Src\Services\MercadoPago\MercadoPagoSubscriptionService;
use App\Src\Support\CurrentCompany;
use App\Transformers\SubscriptionPlanTransformer;
use App\Transformers\SubscriptionTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Suscripción de la empresa activa a un plan de DMIT.
 *
 * Devuelve el link de autorización de Mercado Pago: la suscripción no queda
 * activa hasta que el dueño autoriza el débito allá.
 */
class SubscriptionController extends Controller
{
    public function __construct(
        private MercadoPagoSubscriptionService $service,
        private CurrentCompany $currentCompany,
    ) {
    }

    public function plans(): JsonResponse
    {
        return fractal($this->service->activePlans(), new SubscriptionPlanTransformer())->respond();
    }

    public function current(): JsonResponse
    {
        $subscription = $this->subscriptionOfActiveCompany();

        return fractal($subscription, new SubscriptionTransformer())
            ->parseIncludes('plan')
            ->respond();
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:subscription_plans,id'],
            'payer_email' => ['required', 'email'],
        ]);

        $companyId = $this->currentCompany->id();

        if ($companyId === null) {
            return response()->json(['message' => 'No hay empresa activa.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $subscription = $this->service->subscribe(
                $companyId,
                (int) $datos['plan_id'],
                $datos['payer_email'],
                $request->user()?->id,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable $e) {
            Log::error('Mercado Pago: no se pudo crear la suscripción', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Mercado Pago rechazó el alta de la suscripción.',
                'detail' => $e->getMessage(),
            ], Response::HTTP_BAD_GATEWAY);
        }

        return fractal($subscription, new SubscriptionTransformer())
            ->parseIncludes('plan')
            ->respond(Response::HTTP_CREATED);
    }

    public function cancel(): JsonResponse
    {
        $subscription = $this->subscriptionOfActiveCompany();

        if (! $subscription) {
            return response()->json(['message' => 'La empresa no tiene una suscripción.'], Response::HTTP_NOT_FOUND);
        }

        try {
            $subscription = $this->service->cancel($subscription);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'No se pudo cancelar la suscripción en Mercado Pago.',
                'detail' => $e->getMessage(),
            ], Response::HTTP_BAD_GATEWAY);
        }

        return fractal($subscription, new SubscriptionTransformer())
            ->parseIncludes('plan')
            ->respond();
    }

    private function subscriptionOfActiveCompany(): ?Subscription
    {
        $companyId = $this->currentCompany->id();

        if ($companyId === null) {
            return null;
        }

        return Subscription::withoutGlobalScope(CompanyScope::class)
            ->with('plan')
            ->where('company_id', $companyId)
            ->latest('id')
            ->first();
    }
}
