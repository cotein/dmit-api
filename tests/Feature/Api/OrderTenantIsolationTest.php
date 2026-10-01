<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Antes de T6/T7 cualquier usuario autenticado podía listar y operar pedidos de
 * otras empresas: Order no tenía scope de empresa, el repositorio no filtraba y
 * StoreOrderRequest aceptaba el company_id que mandara el cliente.
 */
class OrderTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function userOf(Company $company): User
    {
        $user = User::factory()->create();
        $user->companies()->attach($company->id);

        return $user;
    }

    public function test_index_only_returns_orders_of_the_current_company(): void
    {
        $mine = Company::factory()->create();
        $other = Company::factory()->create();

        $myOrder = Order::factory()->create(['company_id' => $mine->id]);
        Order::factory()->create(['company_id' => $other->id]);

        Passport::actingAs($this->userOf($mine));

        $this->getJson('/api/orders')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $myOrder->id);
    }

    public function test_it_returns_404_for_an_order_of_another_company(): void
    {
        $mine = Company::factory()->create();
        $other = Company::factory()->create();

        $foreignOrder = Order::factory()->create(['company_id' => $other->id]);

        Passport::actingAs($this->userOf($mine));

        $this->getJson("/api/orders/{$foreignOrder->id}")->assertNotFound();
        $this->putJson("/api/orders/{$foreignOrder->id}", ['date' => '2026-01-01'])->assertNotFound();
        $this->deleteJson("/api/orders/{$foreignOrder->id}")->assertNotFound();

        $this->assertDatabaseHas('orders', ['id' => $foreignOrder->id]);
    }

    public function test_it_rejects_a_customer_of_another_company(): void
    {
        $mine = Company::factory()->create();
        $other = Company::factory()->create();
        $status = Status::factory()->create();
        $foreignCustomer = Customer::factory()->create(['company_id' => $other->id]);

        Passport::actingAs($this->userOf($mine));

        $this->postJson('/api/orders', [
            'company_id' => $other->id,
            'customer_id' => $foreignCustomer->id,
            'status_id' => $status->id,
            'date' => now()->toDateString(),
            'items' => [[
                'product_id' => null,
                'quantity' => 1,
                'unit_price' => 100,
                'iva_percentage' => 21,
                'total' => 121,
            ]],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['customer_id']);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_it_stores_the_order_in_the_company_of_the_user_even_if_another_is_sent(): void
    {
        $mine = Company::factory()->create();
        $other = Company::factory()->create();
        $status = Status::factory()->create();
        $customer = Customer::factory()->create(['company_id' => $mine->id]);
        $product = Product::factory()->create(['company_id' => $mine->id]);

        $user = $this->userOf($mine);

        Passport::actingAs($user);

        $this->postJson('/api/orders', [
            'company_id' => $other->id,
            'customer_id' => $customer->id,
            'status_id' => $status->id,
            'date' => now()->toDateString(),
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => 100,
                'iva_percentage' => 21,
                'total' => 121,
            ]],
        ])->assertCreated();

        $this->assertDatabaseHas('orders', [
            'company_id' => $mine->id,
            'customer_id' => $customer->id,
            'user_id' => $user->id,
        ]);

        $this->assertDatabaseMissing('orders', ['company_id' => $other->id]);
    }
}
