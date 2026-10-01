<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Los requests de pagos resolvían la empresa con Auth::user()->company_id
 * (campo inexistente: users se relaciona con companies por el pivote
 * company_user), así que toda validación filtraba por company_id = null.
 */
class PaymentTenantTest extends TestCase
{
    use RefreshDatabase;

    private function userOf(Company $company): User
    {
        $user = User::factory()->create();
        $user->companies()->attach($company->id);

        return $user;
    }

    public function test_it_rejects_a_payment_for_an_order_of_another_company(): void
    {
        $mine = Company::factory()->create();
        $other = Company::factory()->create();

        $foreignOrder = Order::factory()->create(['company_id' => $other->id]);
        PaymentMethod::create(['company_id' => $mine->id, 'name' => 'Efectivo']);

        Passport::actingAs($this->userOf($mine));

        $this->postJson('/api/payments', [
            'order_id' => $foreignOrder->id,
            'payment_method_id' => 1,
            'amount' => 100,
            'payment_date' => now()->toDateString(),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('order_id');
    }

    public function test_it_creates_a_payment_with_the_company_of_the_order(): void
    {
        $mine = Company::factory()->create();
        $order = Order::factory()->create(['company_id' => $mine->id]);
        $method = PaymentMethod::create(['company_id' => $mine->id, 'name' => 'Transferencia']);

        Passport::actingAs($this->userOf($mine));

        $this->postJson('/api/payments', [
            'order_id' => $order->id,
            'payment_method_id' => $method->id,
            'amount' => 100.50,
            'payment_date' => now()->toDateString(),
        ])->assertCreated();

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'company_id' => $mine->id,
            'amount' => 100.50,
        ]);
    }
}
