<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Customer;
use App\Models\MercadoPagoCharge;
use App\Models\MercadoPagoPayment;
use App\Models\Receipt;
use App\Models\User;
use App\Src\Scopes\CompanyScope;
use App\Src\Services\MercadoPago\MercadoPagoChargeService;
use App\Src\Services\MercadoPago\MercadoPagoSettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * La parte que mueve plata: qué pasa con un pago aprobado, con uno devuelto y
 * con un cobro que venció sin pagarse.
 *
 * Nada de acá llama a Mercado Pago: la conciliación trabaja sobre lo que MP
 * devuelve, así que se puede probar entera con payloads.
 */
class MercadoPagoSettlementTest extends TestCase
{
    use RefreshDatabase;

    private function chargeOf(Company $company, array $extra = []): MercadoPagoCharge
    {
        $customer = Customer::factory()->create(['company_id' => $company->id]);

        return MercadoPagoCharge::withoutGlobalScope(CompanyScope::class)->create(array_merge([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'external_reference' => 'DMIT-' . strtoupper(Str::random(10)),
            'amount' => 2500,
            'currency' => 'ARS',
            'title' => 'Cobro de prueba',
            'concept' => MercadoPagoCharge::CONCEPT_CURRENT_ACCOUNT,
            'status' => MercadoPagoCharge::STATUS_PENDING,
        ], $extra));
    }

    /** Usuario dueño de la empresa: el recibo necesita un user_id. */
    private function ownerOf(Company $company): User
    {
        $user = User::factory()->create();
        $user->companies()->attach($company->id);

        return $user;
    }

    private function pagoAprobado(MercadoPagoCharge $charge, string $mpPaymentId = '555000111'): array
    {
        return [
            'id' => $mpPaymentId,
            'status' => 'approved',
            'status_detail' => 'accredited',
            'transaction_amount' => (float) $charge->amount,
            'external_reference' => $charge->external_reference,
            'payment_method_id' => 'account_money',
            'payment_type_id' => 'account_money',
            'payer' => ['email' => 'cliente@ejemplo.com', 'id' => '99'],
            'transaction_details' => ['net_received_amount' => (float) $charge->amount],
            'date_approved' => now()->toIso8601String(),
        ];
    }

    public function test_un_pago_aprobado_a_cuenta_crea_el_recibo_y_lo_deja_imputado(): void
    {
        $company = Company::factory()->create();
        $charge = $this->chargeOf($company);
        $this->ownerOf($company);

        $settlement = app(MercadoPagoSettlementService::class);
        $payment = $settlement->applyPayment($this->pagoAprobado($charge));

        $this->assertNotNull($payment);
        $this->assertSame('approved', $payment->status);

        $charge = $charge->fresh();
        $this->assertSame(MercadoPagoCharge::STATUS_APPROVED, $charge->status);
        $this->assertNotNull($charge->paid_at);

        // Se creó el recibo y quedó registrado en el cobro.
        $this->assertSame(1, Receipt::count());
        $this->assertNotNull($charge->metadata['settlement']['receipt_id'] ?? null);
        $this->assertSame(
            Receipt::first()->id,
            $charge->metadata['settlement']['receipt_id']
        );
    }

    public function test_el_mismo_pago_no_imputa_dos_veces(): void
    {
        $company = Company::factory()->create();
        $charge = $this->chargeOf($company);
        $this->ownerOf($company);

        $settlement = app(MercadoPagoSettlementService::class);
        $settlement->applyPayment($this->pagoAprobado($charge));
        $settlement->applyPayment($this->pagoAprobado($charge));

        // Un webhook repetido no puede duplicar el cobro ni el recibo.
        $this->assertSame(1, Receipt::count());
        $this->assertSame(1, MercadoPagoPayment::withoutGlobalScope(CompanyScope::class)->count());
    }

    public function test_una_devolucion_sobre_un_pago_imputado_queda_marcada_para_revisar(): void
    {
        $company = Company::factory()->create();
        $charge = $this->chargeOf($company);
        $this->ownerOf($company);

        $settlement = app(MercadoPagoSettlementService::class);
        $settlement->applyPayment($this->pagoAprobado($charge));

        $devolucion = $this->pagoAprobado($charge);
        $devolucion['status'] = 'refunded';
        $settlement->applyPayment($devolucion);

        $charge = $charge->fresh();

        $this->assertSame(MercadoPagoCharge::STATUS_REFUNDED, $charge->status);
        $this->assertSame('refund_after_settlement', $charge->metadata['needs_review']['reason'] ?? null);
        $this->assertStringContainsString('revisar', mb_strtolower($charge->metadata['needs_review']['note'] ?? ''));
    }

    public function test_una_devolucion_sin_imputacion_no_marca_nada(): void
    {
        $company = Company::factory()->create();
        $charge = $this->chargeOf($company);

        $devolucion = $this->pagoAprobado($charge);
        $devolucion['status'] = 'refunded';

        app(MercadoPagoSettlementService::class)->applyPayment($devolucion);

        $this->assertNull($charge->fresh()->metadata['needs_review'] ?? null);
    }

    public function test_los_cobros_vencidos_se_marcan_y_no_se_concilians(): void
    {
        $company = Company::factory()->create();

        $vencido = $this->chargeOf($company, ['expires_at' => now()->subDay()]);
        $venceHoy = $this->chargeOf($company, ['expires_at' => now()->addDay()]);
        $sinVencimiento = $this->chargeOf($company);
        $yaPagado = $this->chargeOf($company, [
            'expires_at' => now()->subDay(),
            'status' => MercadoPagoCharge::STATUS_APPROVED,
        ]);

        $marcados = app(MercadoPagoChargeService::class)->expireOverdue();

        $this->assertSame(1, $marcados);
        $this->assertSame(MercadoPagoCharge::STATUS_EXPIRED, $vencido->fresh()->status);
        $this->assertSame(MercadoPagoCharge::STATUS_PENDING, $venceHoy->fresh()->status);
        $this->assertSame(MercadoPagoCharge::STATUS_PENDING, $sinVencimiento->fresh()->status);
        $this->assertSame(MercadoPagoCharge::STATUS_APPROVED, $yaPagado->fresh()->status);
    }

    public function test_el_comando_de_conciliacion_marca_los_vencidos(): void
    {
        $company = Company::factory()->create();
        $this->chargeOf($company, ['expires_at' => now()->subHour()]);

        $this->artisan('mercadopago:conciliar')
            ->expectsOutputToContain('Cobros vencidos marcados: 1')
            ->assertExitCode(0);

        $this->assertSame(
            MercadoPagoCharge::STATUS_EXPIRED,
            MercadoPagoCharge::withoutGlobalScope(CompanyScope::class)->first()->status
        );
    }
}
