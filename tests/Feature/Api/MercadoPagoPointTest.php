<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\MercadoPagoCharge;
use App\Models\MercadoPagoWebhookEvent;
use App\Models\User;
use App\Src\Scopes\CompanyScope;
use App\Src\Services\MercadoPago\MercadoPagoPointService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Cobro con la terminal Point.
 *
 * Los endpoints contra Mercado Pago no se pueden probar sin terminal ni
 * credenciales, pero sí lo que decide el comportamiento: la traducción de
 * estados y por dónde entra la notificación.
 */
class MercadoPagoPointTest extends TestCase
{
    use RefreshDatabase;

    private const SECRETO = 'clave-secreta-de-prueba';

    protected function setUp(): void
    {
        parent::setUp();

        config(['mercadopago.webhook_secret' => self::SECRETO]);
    }

    private function userOf(Company $company): User
    {
        $user = User::factory()->create();
        $user->companies()->attach($company->id);

        return $user;
    }

    public function test_los_estados_del_intent_se_traducen_a_los_del_cobro(): void
    {
        $point = app(MercadoPagoPointService::class);

        $this->assertSame(MercadoPagoCharge::STATUS_PENDING, $point->mapIntentState('OPEN'));
        $this->assertSame(MercadoPagoCharge::STATUS_IN_PROCESS, $point->mapIntentState('ON_TERMINAL'));
        $this->assertSame(MercadoPagoCharge::STATUS_IN_PROCESS, $point->mapIntentState('PROCESSING'));
        $this->assertSame(MercadoPagoCharge::STATUS_APPROVED, $point->mapIntentState('FINISHED'));
        $this->assertSame(MercadoPagoCharge::STATUS_CANCELLED, $point->mapIntentState('CANCELED'));
        $this->assertSame(MercadoPagoCharge::STATUS_REJECTED, $point->mapIntentState('ERROR'));

        // Un estado que no conocemos NO puede quedar como pagado.
        $this->assertSame(MercadoPagoCharge::STATUS_PENDING, $point->mapIntentState('LO_QUE_SEA'));
        $this->assertSame(MercadoPagoCharge::STATUS_PENDING, $point->mapIntentState(null));
    }

    public function test_la_notificacion_de_la_terminal_entra_por_su_propio_camino(): void
    {
        $company = Company::factory()->create();

        // Cobro ya mandado a la terminal, sin cuenta conectada a propósito.
        $charge = MercadoPagoCharge::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $company->id,
            'external_reference' => 'DMIT-' . strtoupper(Str::random(10)),
            'amount' => 3200,
            'currency' => 'ARS',
            'channel' => MercadoPagoCharge::CHANNEL_POINT,
            'mp_order_id' => 'INTENT-123',
            'status' => MercadoPagoCharge::STATUS_IN_PROCESS,
        ]);

        $ts = (string) time();
        $firma = hash_hmac('sha256', 'id:INTENT-123;request-id:req-point;ts:' . $ts . ';', self::SECRETO);

        $this->postJson('/api/mercado-pago/webhook', [
            'id' => 'evt-point-1',
            'type' => 'point_integration_wh',
            'action' => 'payment.created',
            'data' => ['id' => 'INTENT-123'],
        ], ['x-signature' => 'ts=' . $ts . ',v1=' . $firma, 'x-request-id' => 'req-point'])
            ->assertStatus(200)
            ->assertJson(['status' => 'accepted']);

        $evento = MercadoPagoWebhookEvent::where('mp_event_id', 'evt-point-1')->first();

        // Entró por la rama de la terminal: encontró el cobro por el intent y pidió
        // la cuenta (que no existe) en vez de intentar leerlo como pago suelto.
        $this->assertSame('point_integration_wh', $evento->topic);
        $this->assertSame(MercadoPagoWebhookEvent::STATUS_FAILED, $evento->status);
        $this->assertStringContainsString(
            'no tiene una cuenta de Mercado Pago conectada',
            (string) $evento->error
        );
    }

    public function test_un_intent_desconocido_se_prueba_como_pago_y_no_rompe(): void
    {
        // Sin cobro con ese id y sin cuentas conectadas: no hay token, y el evento
        // queda fallado con un motivo claro (no un 500).
        $ts = (string) time();
        $firma = hash_hmac('sha256', 'id:999888;request-id:req-point2;ts:' . $ts . ';', self::SECRETO);

        $this->postJson('/api/mercado-pago/webhook', [
            'id' => 'evt-point-2',
            'type' => 'point_integration_ipn',
            'data' => ['id' => '999888'],
        ], ['x-signature' => 'ts=' . $ts . ',v1=' . $firma, 'x-request-id' => 'req-point2'])
            ->assertStatus(200)
            ->assertJson(['status' => 'accepted']);

        $this->assertSame(
            MercadoPagoWebhookEvent::STATUS_FAILED,
            MercadoPagoWebhookEvent::where('mp_event_id', 'evt-point-2')->first()->status
        );
    }

    public function test_los_estados_de_la_orden_se_traducen_a_los_del_cobro(): void
    {
        $pos = app(\App\Src\Services\MercadoPago\MercadoPagoPosService::class);

        $this->assertSame(MercadoPagoCharge::STATUS_PENDING, $pos->mapOrderState('created'));
        $this->assertSame(MercadoPagoCharge::STATUS_APPROVED, $pos->mapOrderState('processed'));
        $this->assertSame(MercadoPagoCharge::STATUS_IN_PROCESS, $pos->mapOrderState('action_required'));
        $this->assertSame(MercadoPagoCharge::STATUS_CANCELLED, $pos->mapOrderState('canceled'));
        $this->assertSame(MercadoPagoCharge::STATUS_EXPIRED, $pos->mapOrderState('expired'));
        $this->assertSame(MercadoPagoCharge::STATUS_REJECTED, $pos->mapOrderState('failed'));

        // Un estado desconocido no puede quedar como pagado.
        $this->assertSame(MercadoPagoCharge::STATUS_PENDING, $pos->mapOrderState('lo_que_sea'));
        $this->assertSame(MercadoPagoCharge::STATUS_PENDING, $pos->mapOrderState(null));
    }

    public function test_la_notificacion_de_la_orden_del_qr_entra_por_el_camino_del_mostrador(): void
    {
        $company = Company::factory()->create();

        $charge = MercadoPagoCharge::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $company->id,
            'external_reference' => 'DMIT-' . strtoupper(Str::random(10)),
            'amount' => 1800,
            'currency' => 'ARS',
            'channel' => MercadoPagoCharge::CHANNEL_POS_QR,
            'mp_order_id' => 'ORDTST01ABC',
            'status' => MercadoPagoCharge::STATUS_PENDING,
        ]);

        $ts = (string) time();
        $firma = hash_hmac('sha256', 'id:ORDTST01ABC;request-id:req-order;ts:' . $ts . ';', self::SECRETO);

        $this->postJson('/api/mercado-pago/webhook', [
            'id' => 'evt-order-1',
            'type' => 'order',
            'action' => 'order.updated',
            'data' => ['id' => 'ORDTST01ABC'],
        ], ['x-signature' => 'ts=' . $ts . ',v1=' . $firma, 'x-request-id' => 'req-order'])
            ->assertStatus(200)
            ->assertJson(['status' => 'accepted']);

        $evento = MercadoPagoWebhookEvent::where('mp_event_id', 'evt-order-1')->first();

        $this->assertSame('order', $evento->topic);
        $this->assertSame(MercadoPagoWebhookEvent::STATUS_FAILED, $evento->status);
        $this->assertStringContainsString('no tiene una cuenta de Mercado Pago conectada', (string) $evento->error);
    }

    public function test_el_listado_de_terminales_sin_cuenta_avisa(): void
    {
        $company = Company::factory()->create();

        Passport::actingAs($this->userOf($company));

        $this->getJson('/api/mercado-pago/devices')
            ->assertStatus(422)
            ->assertJson(['message' => 'La empresa no tiene una cuenta de Mercado Pago conectada.']);
    }

    public function test_no_se_puede_mandar_un_cobro_a_la_terminal_sin_token(): void
    {
        $this->postJson('/api/mercado-pago/charges/1/point', ['device_id' => 'ABC123'])->assertStatus(401);
    }
}
