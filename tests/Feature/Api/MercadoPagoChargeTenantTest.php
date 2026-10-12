<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\MercadoPagoAccount;
use App\Models\MercadoPagoCharge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * El módulo de pagos mueve plata: lo que más importa probar no es que cree un
 * cobro, sino que una empresa NO pueda ver ni tocar el de otra.
 *
 * Los cobros de la otra empresa se crean ANTES de autenticar porque el trait de
 * empresa pisa el company_id con la empresa activa cuando hay usuario.
 */
class MercadoPagoChargeTenantTest extends TestCase
{
    use RefreshDatabase;

    private function userOf(Company $company): User
    {
        $user = User::factory()->create();
        $user->companies()->attach($company->id);

        return $user;
    }

    private function chargeOf(Company $company, array $extra = []): MercadoPagoCharge
    {
        return MercadoPagoCharge::create(array_merge([
            'company_id' => $company->id,
            'external_reference' => 'DMIT-' . strtoupper(Str::random(10)),
            'amount' => 1500,
            'currency' => 'ARS',
            'title' => 'Cobro de prueba',
            'status' => MercadoPagoCharge::STATUS_PENDING,
        ], $extra));
    }

    private function connectedAccountOf(Company $company): MercadoPagoAccount
    {
        return MercadoPagoAccount::withoutGlobalScope(\App\Src\Scopes\CompanyScope::class)->create([
            'company_id' => $company->id,
            'mp_user_id' => '123456789',
            'access_token' => 'TEST-token-de-prueba',
            'refresh_token' => 'TEST-refresh-de-prueba',
            'token_expires_at' => now()->addHours(4),
            'status' => MercadoPagoAccount::STATUS_CONNECTED,
            'connected_at' => now(),
        ]);
    }

    public function test_el_listado_solo_trae_los_cobros_de_la_empresa_activa(): void
    {
        $mia = Company::factory()->create();
        $ajena = Company::factory()->create();

        $this->chargeOf($ajena);
        $miCobro = $this->chargeOf($mia);

        Passport::actingAs($this->userOf($mia));

        $this->getJson('/api/mercado-pago/charges')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.external_reference', $miCobro->external_reference);
    }

    public function test_no_se_puede_ver_un_cobro_de_otra_empresa(): void
    {
        $mia = Company::factory()->create();
        $ajena = Company::factory()->create();

        $ajeno = $this->chargeOf($ajena);

        Passport::actingAs($this->userOf($mia));

        $this->getJson('/api/mercado-pago/charges/' . $ajeno->id)->assertStatus(404);
    }

    public function test_no_se_puede_anular_un_cobro_de_otra_empresa(): void
    {
        $mia = Company::factory()->create();
        $ajena = Company::factory()->create();

        $ajeno = $this->chargeOf($ajena);

        Passport::actingAs($this->userOf($mia));

        $this->postJson('/api/mercado-pago/charges/' . $ajeno->id . '/cancel')->assertStatus(404);

        // Y sigue pendiente: el intento no tocó nada.
        $this->assertSame(
            MercadoPagoCharge::STATUS_PENDING,
            $ajeno->fresh()->status
        );
    }

    public function test_no_se_puede_pedir_la_empresa_de_otro_con_el_header(): void
    {
        $mia = Company::factory()->create();
        $ajena = Company::factory()->create();

        Passport::actingAs($this->userOf($mia));

        $this->getJson('/api/mercado-pago/charges', ['X-Company-Id' => (string) $ajena->id])
            ->assertStatus(403);
    }

    public function test_sin_cuenta_conectada_no_se_genera_un_cobro(): void
    {
        $company = Company::factory()->create();

        Passport::actingAs($this->userOf($company));

        $this->postJson('/api/mercado-pago/charges', [
            'amount' => 2500,
            'concept' => 'manual',
        ])
            ->assertStatus(422)
            ->assertJson(['message' => 'La empresa no tiene una cuenta de Mercado Pago conectada.']);

        // No queda un cobro huérfano dando vueltas.
        $this->assertSame(0, MercadoPagoCharge::count());
    }

    public function test_la_cuenta_se_informa_sin_conectar_cuando_no_hay_conexion(): void
    {
        $company = Company::factory()->create();

        Passport::actingAs($this->userOf($company));

        $this->getJson('/api/mercado-pago/account')
            ->assertStatus(200)
            ->assertJson(['is_connected' => false, 'data' => null]);
    }

    public function test_la_cuenta_conectada_no_expone_los_tokens(): void
    {
        $company = Company::factory()->create();
        $this->connectedAccountOf($company);

        Passport::actingAs($this->userOf($company));

        $respuesta = $this->getJson('/api/mercado-pago/account')->assertStatus(200);

        $this->assertTrue($respuesta->json('data.is_connected'));
        $this->assertStringNotContainsString('TEST-token-de-prueba', $respuesta->getContent());
        $this->assertStringNotContainsString('TEST-refresh-de-prueba', $respuesta->getContent());
    }

    public function test_la_conciliacion_a_pedido_solo_toca_la_empresa_activa(): void
    {
        $mia = Company::factory()->create();
        $ajena = Company::factory()->create();

        // Los dos vencidos: sólo el de la empresa activa se puede marcar.
        $mio = $this->chargeOf($mia, ['expires_at' => now()->subDay()]);
        $ajeno = $this->chargeOf($ajena, ['expires_at' => now()->subDay()]);

        Passport::actingAs($this->userOf($mia));

        $this->postJson('/api/mercado-pago/reconcile')
            ->assertStatus(200)
            ->assertJsonPath('data.expirados', 1)
            ->assertJsonPath('data.cobros_revisados', 0);

        $this->assertSame(MercadoPagoCharge::STATUS_EXPIRED, $mio->fresh()->status);
        $this->assertSame(MercadoPagoCharge::STATUS_PENDING, $ajeno->fresh()->status);
    }

    public function test_la_conciliacion_sin_token_no_se_puede_pedir(): void
    {
        $this->postJson('/api/mercado-pago/reconcile')->assertStatus(401);
    }

    public function test_anular_avisa_a_mercado_pago_pero_no_depende_de_el(): void
    {
        $company = Company::factory()->create();

        // Cobro de mostrador con orden en MP, pero SIN cuenta conectada: el aviso
        // a Mercado Pago va a fallar y la anulación local tiene que hacerse igual.
        $charge = $this->chargeOf($company, [
            'channel' => MercadoPagoCharge::CHANNEL_POS_QR,
            'mp_order_id' => 'ORDTST01PRUEBA',
        ]);

        Passport::actingAs($this->userOf($company));

        $this->postJson('/api/mercado-pago/charges/' . $charge->id . '/cancel')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');

        $charge = $charge->fresh();
        $this->assertSame(MercadoPagoCharge::STATUS_CANCELLED, $charge->status);
        // Sin sufijo: MP no confirmó, y eso no impide anular.
        $this->assertSame('Anulado desde DMIT', $charge->status_detail);
    }

    public function test_no_se_puede_anular_un_cobro_ya_pagado(): void
    {
        $company = Company::factory()->create();

        $charge = $this->chargeOf($company, ['status' => MercadoPagoCharge::STATUS_APPROVED]);

        Passport::actingAs($this->userOf($company));

        $this->postJson('/api/mercado-pago/charges/' . $charge->id . '/cancel')
            ->assertStatus(422)
            ->assertJson(['message' => 'El cobro ya está aprobado: para revertirlo hace falta una devolución.']);

        $this->assertSame(MercadoPagoCharge::STATUS_APPROVED, $charge->fresh()->status);
    }

    public function test_el_importe_tiene_que_ser_mayor_a_cero(): void
    {
        $company = Company::factory()->create();
        $this->connectedAccountOf($company);

        Passport::actingAs($this->userOf($company));

        $this->postJson('/api/mercado-pago/charges', [
            'amount' => 0,
            'concept' => 'manual',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');
    }
}
