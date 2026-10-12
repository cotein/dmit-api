<?php

namespace Tests\Feature\Api;

use App\Models\MercadoPagoWebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El webhook es público y lo llama Mercado Pago: acá se prueba que sólo entre lo
 * firmado, que un reintento no se procese dos veces y que nunca devuelva 500
 * (un 500 hace que Mercado Pago reintente en vano).
 */
class MercadoPagoWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRETO = 'clave-secreta-de-prueba';

    protected function setUp(): void
    {
        parent::setUp();

        config(['mercadopago.webhook_secret' => self::SECRETO]);
    }

    /** @return array<string, string> headers de una notificación firmada */
    private function cabeceras(string $dataId, string $requestId = 'req-1'): array
    {
        $ts = (string) time();
        $manifest = 'id:' . $dataId . ';request-id:' . $requestId . ';ts:' . $ts . ';';

        return [
            'x-signature' => 'ts=' . $ts . ',v1=' . hash_hmac('sha256', $manifest, self::SECRETO),
            'x-request-id' => $requestId,
        ];
    }

    public function test_rechaza_una_notificacion_sin_firma_valida(): void
    {
        $this->postJson('/api/mercado-pago/webhook', [
            'id' => 'evt-1',
            'type' => 'payment',
            'data' => ['id' => '999'],
        ], ['x-signature' => 'ts=1,v1=falsa'])
            ->assertStatus(200)
            ->assertJson(['status' => 'invalid_signature']);

        $evento = MercadoPagoWebhookEvent::where('mp_event_id', 'evt-1')->first();

        $this->assertNotNull($evento);
        $this->assertFalse($evento->signature_valid);
        $this->assertSame(MercadoPagoWebhookEvent::STATUS_IGNORED, $evento->status);
    }

    public function test_acepta_una_notificacion_firmada(): void
    {
        $this->postJson('/api/mercado-pago/webhook', [
            'id' => 'evt-2',
            'type' => 'payment',
            'data' => ['id' => '999'],
        ], $this->cabeceras('999'))
            ->assertStatus(200)
            ->assertJson(['status' => 'accepted']);

        $evento = MercadoPagoWebhookEvent::where('mp_event_id', 'evt-2')->first();

        $this->assertTrue($evento->signature_valid);
        $this->assertSame('payment', $evento->topic);
        $this->assertSame('999', $evento->resource_id);
    }

    public function test_un_reintento_del_mismo_evento_no_se_procesa_dos_veces(): void
    {
        $cuerpo = ['id' => 'evt-3', 'type' => 'payment', 'data' => ['id' => '999']];
        $cabeceras = $this->cabeceras('999');

        $this->postJson('/api/mercado-pago/webhook', $cuerpo, $cabeceras)->assertStatus(200);

        $this->postJson('/api/mercado-pago/webhook', $cuerpo, $cabeceras)
            ->assertStatus(200)
            ->assertJson(['status' => 'duplicate']);

        $this->assertSame(1, MercadoPagoWebhookEvent::where('mp_event_id', 'evt-3')->count());
    }

    public function test_un_topico_desconocido_no_rompe(): void
    {
        $this->postJson('/api/mercado-pago/webhook', [
            'id' => 'evt-4',
            'type' => 'topico_que_no_existe',
            'data' => ['id' => '1'],
        ], $this->cabeceras('1'))
            ->assertStatus(200)
            ->assertJson(['status' => 'accepted']);

        $this->assertSame(
            MercadoPagoWebhookEvent::STATUS_IGNORED,
            MercadoPagoWebhookEvent::where('mp_event_id', 'evt-4')->first()->status
        );
    }

    public function test_sin_secreto_configurado_no_acepta_nada(): void
    {
        config(['mercadopago.webhook_secret' => null]);

        $this->postJson('/api/mercado-pago/webhook', [
            'id' => 'evt-5',
            'type' => 'payment',
            'data' => ['id' => '999'],
        ], $this->cabeceras('999'))
            ->assertStatus(200)
            ->assertJson(['status' => 'invalid_signature']);
    }
}
