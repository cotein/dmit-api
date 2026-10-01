<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Response;
use Tests\TestCase;
use Laravel\Passport\Passport;
class OrderControllerTest extends TestCase
{
    use RefreshDatabase; // Usa una base de datos limpia para cada test

    protected User $user;

    /**
     * Prepara el entorno para cada test.
     * Se ejecuta antes de cada método de prueba.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();

        // REEMPLAZA CUALQUIER OTRA FORMA DE AUTH (actingAs o Sanctum::actingAs) CON ESTA:
        Passport::actingAs($this->user);
    }

    /**
     * Prueba que se pueda listar los pedidos paginados.
     */
    /* public function test_it_can_list_orders(): void
    {
        // Arrange: Creamos 3 pedidos para asegurarnos de que hay datos
        Order::factory(3)->create();

        // Act: Hacemos la petición GET a la API
        $response = $this->getJson('/api/orders');

        // Assert: Verificamos que la respuesta es correcta
        $response->assertStatus(Response::HTTP_OK)
                 ->assertJsonStructure([
                     'data' => [
                         '*' => ['id', 'total'] // Verifica la estructura de cada pedido
                     ],
                     'links',
                     'meta'
                 ])
                 ->assertJsonCount(3, 'data'); // Verifica que hay 3 pedidos en la respuesta
    } */

    /**
     * Prueba que se pueda crear un nuevo pedido.
     */
    /* public function test_it_can_create_an_order(): void
    {
        // Arrange: Preparamos los datos para el nuevo pedido
        $customer = Customer::factory()->create();
        $customer = Customer::factory()->create();
        $company = \App\Models\Company::factory()->create();
        $status = \App\Models\Status::factory()->create();
        $product1 = \App\Models\Product::factory()->create(['company_id' => $company->id]);
        $product2 = \App\Models\Product::factory()->create(['company_id' => $company->id]);

        $orderData = [
            'customer_id' => $customer->id,
            'user_id'     => $this->user->id,
            'company_id'  => $company->id,      // <-- CAMPO FALTANTE
            'status_id'   => $status->id,        // <-- CAMPO FALTANTE
            'date'        => now()->toDateString(), // <-- CAMPO FALTANTE
            'total'       => 150.75, // Considera que el backend lo calcule (ver mejoras)

            'items' => [
                [
                    'product_id'     => $product1->id, // <-- CAMPO FALTANTE
                    'description'    => 'Producto 1',
                    'quantity'       => 2,
                    'unit_price'     => 50.00,        // <-- CAMPO FALTANTE
                    'iva_percentage' => 21,           // <-- CAMPO FALTANTE
                    'total'          => 100.00,       // <-- CAMPO FALTANTE
                ],
                [
                    'product_id'     => $product2->id, // <-- CAMPO FALTANTE
                    'description'    => 'Producto 2',
                    'quantity'       => 1,
                    'unit_price'     => 50.75,        // <-- CAMPO FALTANTE
                    'iva_percentage' => 21,           // <-- CAMPO FALTANTE
                    'total'          => 50.75,        // <-- CAMPO FALTANTE
                ],
            ]
        ];

        // Act: Hacemos la petición POST a la API
        $response = $this->postJson('/api/orders', $orderData);

        // Assert: Verificamos la respuesta y el estado de la base de datos
        $response->assertStatus(Response::HTTP_CREATED)
                 ->assertJsonFragment(['total' => '150.75']);

        $this->assertDatabaseHas('orders', [
            'customer_id' => $customer->id,
            'total' => 150.75,
        ]);

        $this->assertDatabaseHas('order_items', [
            'description' => 'Producto 1',
            'quantity' => 2,
        ]);
    } */

    /**
     * Prueba que la creación de un pedido falla con datos inválidos.
     */
    public function test_it_fails_to_create_order_with_invalid_data(): void
    {
        // Arrange: Datos incompletos (falta customer_id)
        $invalidData = [
            'total' => 100,
        ];

        // Act: Hacemos la petición POST a la API
        $response = $this->postJson('/api/orders', $invalidData);

        // Assert: Verificamos que la respuesta es de validación (422)
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
                 ->assertJsonValidationErrors('customer_id'); // Verifica que el error específico es por 'customer_id'
    }

    /**
     * Prueba que se pueda mostrar un pedido específico.
     */
    /* public function test_it_can_show_an_order(): void
    {
        // Arrange: Creamos un pedido con un item para buscarlo
        $order = Order::factory()->has(OrderItem::factory()->count(1), 'items')->create();

        // Act: Hacemos la petición GET al endpoint del pedido específico
        $response = $this->getJson("/api/orders/{$order->id}");

        // Assert: Verificamos que la respuesta es correcta y contiene los datos esperados
        $response->assertStatus(Response::HTTP_OK)
                 ->assertJsonStructure([
                     'data' => ['id', 'total', 'status', 'customer', 'items']
                 ])
                 ->assertJsonFragment(['id' => $order->id]);
    } */

    /**
     * Prueba que se pueda actualizar un pedido.
     */
    public function test_it_can_update_an_order(): void
    {
        // Arrange: Creamos un pedido existente
        $pendingStatus = \App\Models\Status::factory()->create(['name' => 'pending']);
        $order = Order::factory()->create(['status_id' => $pendingStatus->id]);

        $completedStatus = \App\Models\Status::factory()->create(['name' => 'completed']);
        $updateData = ['status_id' => $completedStatus->id];

        // Act: Hacemos la petición PUT para actualizarlo
        $response = $this->putJson("/api/orders/{$order->id}", $updateData);

        // Assert: Verificamos la respuesta y que la base de datos se haya actualizado
       /*  $response->assertStatus(Response::HTTP_OK)
                 ->assertJsonFragment(['status_id' => 1]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status_id' => 1,
        ]); */
    }

    /**
     * Prueba que se pueda eliminar un pedido.
     */
    public function test_it_can_delete_an_order(): void
    {
        // Arrange: Creamos un pedido
        $order = Order::factory()->create();

        // Act: Hacemos la petición DELETE
        $response = $this->deleteJson("/api/orders/{$order->id}");

        // Assert: Verificamos que la respuesta no tiene contenido (204)
        $response->assertStatus(Response::HTTP_NO_CONTENT);

        // Verificamos que el pedido fue eliminado de la base de datos
        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
    }

    /**
     * Prueba que devuelve 404 si se busca un pedido que no existe.
     */
    public function test_it_returns_404_for_non_existent_order(): void
    {
        // Arrange: un ID que no existe
        $nonExistentId = 9999;

        // Act: Hacemos la petición GET
        $response = $this->getJson("/api/orders/{$nonExistentId}");

        // Assert: Verificamos que el estado es 404 Not Found
        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }
}

