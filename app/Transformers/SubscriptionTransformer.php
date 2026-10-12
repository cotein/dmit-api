<?php

namespace App\Transformers;

use App\Models\Subscription;
use League\Fractal\TransformerAbstract;

class SubscriptionTransformer extends TransformerAbstract
{
    protected array $availableIncludes = ['plan'];

    public function transform(Subscription $subscription): array
    {
        return [
            'id' => (int) $subscription->id,
            'status' => $subscription->status,
            'plan_id' => (int) $subscription->plan_id,
            'payer_email' => $subscription->payer_email,
            'started_at' => $subscription->started_at?->toIso8601String(),
            'next_payment_at' => $subscription->next_payment_at?->toIso8601String(),
            'last_payment_at' => $subscription->last_payment_at?->toIso8601String(),
            'cancelled_at' => $subscription->cancelled_at?->toIso8601String(),
            // El acceso al sistema se decide con esto, no con una fecha calculada.
            'is_active' => $subscription->isActive(),
        ];
    }

    public function includePlan(Subscription $subscription)
    {
        if ($subscription->plan) {
            return $this->item($subscription->plan, new SubscriptionPlanTransformer());
        }
    }
}
