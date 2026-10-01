<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * El total del pedido lo definía el cliente: recalculateOrderTotal() sólo sumaba
 * los items.*.total que llegaban en el request.
 */
class OrderTotalsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    private Customer $customer;

    private Product $product;

    private Status $status;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->user = User::factory()->create();
        $this->user->companies()->attach($this->company->id);

        $this->customer = Customer::factory()->create(['company_id' => $this->company->id]);
        $this->product = Product::factory()->create(['company_id' => $this->company->id]);
        $this->status = Status::factory()->create();

        Passport::actingAs($this->user);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(float $clientTotal): array
    {
        return [
            'customer_id' => $this->customer->id,
            'status_id' => $this->status->id,
            'date' => now()->toDateString(),
            'items' => [[
                'product_id' => $this->product->id,
                'quantity' => 2,
                'unit_price' => 100,
                'iva_percentage' => 21,
                'total' => $clientTotal,
            ]],
        ];
    }

    public function test_it_stores_the_total_computed_by_the_server(): void
    {
        Log::spy();

        // El cliente manda 999; el servidor calcula (2 x 100) + 21% = 242.
        $this->postJson('/api/orders', $this->payload(999))
            ->assertCreated()
            ->assertJsonPath('data.total', fn ($value) => (float) $value === 242.0);

        $this->assertDatabaseHas('order_items', [
            'product_id' => $this->product->id,
            'neto_import' => 200,
            'iva_import' => 42,
            'total' => 242,
        ]);

        $this->assertDatabaseHas('orders', ['total' => 242]);

        Log::shouldHaveReceived('warning')
            ->withArgs(fn ($message) => $message === 'Orden con total de ítem inconsistente');
    }

    public function test_in_strict_mode_it_rejects_a_total_that_does_not_match(): void
    {
        config(['orders.strict_totals' => true]);

        $this->postJson('/api/orders', $this->payload(999))
            ->assertStatus(422)
            ->assertJsonValidationErrors('items');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_in_strict_mode_it_accepts_the_matching_total(): void
    {
        config(['orders.strict_totals' => true]);

        $this->postJson('/api/orders', $this->payload(242))
            ->assertCreated()
            ->assertJsonPath('data.total', fn ($value) => (float) $value === 242.0);
    }

    public function test_it_applies_the_item_discount_before_the_iva(): void
    {
        config(['orders.strict_totals' => true]);

        // 2 x 100 = 200, -10% = 180, +21% = 217.80
        $payload = $this->payload(217.80);
        $payload['items'][0]['discount_percentage'] = 10;

        $this->postJson('/api/orders', $payload)
            ->assertCreated()
            ->assertJsonPath('data.total', fn ($value) => (float) $value === 217.8);

        $this->assertDatabaseHas('order_items', [
            'neto_import' => 180,
            'discount_import' => 20,
            'iva_import' => 37.8,
            'total' => 217.8,
        ]);
    }
}
