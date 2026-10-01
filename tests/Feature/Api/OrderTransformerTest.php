<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * El transformer de pedidos apuntaba a $order->orderItems (relación inexistente)
 * y el accessor Order::getStatusAttribute() reventaba cuando status_id era null.
 */
class OrderTransformerTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_the_order_items_and_the_status_name(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $user->companies()->attach($company->id);

        $status = Status::factory()->create(['name' => 'pending']);
        $customer = Customer::factory()->create(['company_id' => $company->id]);
        $product = Product::factory()->create(['company_id' => $company->id]);

        $order = Order::factory()->create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'status_id' => $status->id,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 100,
            'total' => 200,
        ]);

        Passport::actingAs($user);

        $this->getJson("/api/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.status_id', $status->id)
            ->assertJsonCount(1, 'data.items.data')
            ->assertJsonPath('data.items.data.0.id', $order->items()->first()->id)
            ->assertJsonPath('data.customer.data.id', $customer->id);
    }

    public function test_index_does_not_break_when_the_order_has_no_status(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $user->companies()->attach($company->id);

        Order::factory()->create([
            'company_id' => $company->id,
            'status_id' => null,
        ]);

        Passport::actingAs($user);

        $this->getJson('/api/orders')
            ->assertOk()
            ->assertJsonPath('data.0.status', null);
    }
}
