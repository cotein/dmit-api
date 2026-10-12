<?php

namespace App\Transformers;

use App\Models\SubscriptionPlan;
use League\Fractal\TransformerAbstract;

class SubscriptionPlanTransformer extends TransformerAbstract
{
    public function transform(SubscriptionPlan $plan): array
    {
        return [
            'id' => (int) $plan->id,
            'name' => $plan->name,
            'description' => $plan->description,
            'amount' => (float) $plan->amount,
            'currency' => $plan->currency,
            'period' => $plan->period,
            'free_trial_days' => (int) $plan->free_trial_days,
            'active' => (bool) $plan->active,
        ];
    }
}
