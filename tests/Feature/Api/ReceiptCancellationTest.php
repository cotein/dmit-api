<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Receipt;
use App\Models\ReceiptPayment;
use App\Models\SaleInvoices;
use App\Models\User;
use App\Src\Constantes;
use App\Src\Repositories\CustomerCuentaCorrienteRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Anulación de recibos: el recibo no se borra, se marca con motivo y fecha, y
 * se revierte su efecto sobre los comprobantes y sobre la cuenta corriente del
 * cliente.
 */
class ReceiptCancellationTest extends TestCase
{
    use RefreshDatabase;

    private function userOf(Company $company): User
    {
        $user = User::factory()->create();
        $user->companies()->attach($company->id);

        return $user;
    }

    private function receiptWithPayment(Company $company, Customer $customer): Receipt
    {
        $receipt = new Receipt();
        $receipt->company_id = $company->id;
        $receipt->customer_id = $customer->id;
        $receipt->total = 1000;
        $receipt->saldo = 0;
        $receipt->save();

        $payment = new ReceiptPayment();
        $payment->receipt_id = $receipt->id;
        $payment->date = now()->toDateString();
        $payment->total = 1000;
        $payment->save();

        return $receipt;
    }

    public function test_it_cancels_a_receipt_and_reverts_the_imputation(): void
    {
        $company = Company::factory()->create();
        $customer = Customer::factory()->create(['company_id' => $company->id]);

        $invoice = SaleInvoices::factory()->create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'status_id' => Constantes::CANCELADA,
        ]);

        $receipt = $this->receiptWithPayment($company, $customer);

        $receipt->invoices()->attach($invoice->id, [
            'percentage_payment' => 100,
            'percentage_paid_history' => 100,
            'import_payment' => 1000,
            'import_paid_history' => 1000,
        ]);

        app(CustomerCuentaCorrienteRepository::class)->store($receipt);

        $this->assertDatabaseHas('customer_cuenta_corrientes', [
            'cuotaable_id' => $receipt->id,
            'cuotaable_type' => Receipt::class,
        ]);

        Passport::actingAs($this->userOf($company));

        $this->postJson('/api/receipt/' . $receipt->id . '/anular', ['reason' => 'Se cargó dos veces'])
            ->assertOk();

        $this->assertDatabaseHas('receipts', [
            'id' => $receipt->id,
            'cancel_reason' => 'Se cargó dos veces',
        ]);

        $this->assertNotNull(Receipt::find($receipt->id)->cancelled_at);

        // La imputación del recibo anulado ya no existe.
        $this->assertDatabaseMissing('receipt_sale_invoices', [
            'receipt_id' => $receipt->id,
        ]);

        // El comprobante vuelve a estar adeudado: nadie lo paga.
        $this->assertSame(Constantes::ADEUDADA, (int) SaleInvoices::find($invoice->id)->status_id);

        // El pago deja de pesar en la cuenta corriente del cliente.
        $this->assertDatabaseMissing('customer_cuenta_corrientes', [
            'cuotaable_id' => $receipt->id,
            'cuotaable_type' => Receipt::class,
        ]);
    }

    public function test_it_keeps_the_invoice_cancelled_when_another_receipt_still_pays_it(): void
    {
        $company = Company::factory()->create();
        $customer = Customer::factory()->create(['company_id' => $company->id]);

        $invoice = SaleInvoices::factory()->create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'status_id' => Constantes::CANCELADA,
        ]);

        $first = $this->receiptWithPayment($company, $customer);
        $first->invoices()->attach($invoice->id, [
            'percentage_payment' => 60,
            'percentage_paid_history' => 60,
            'import_payment' => 600,
            'import_paid_history' => 600,
        ]);

        $second = $this->receiptWithPayment($company, $customer);
        $second->invoices()->attach($invoice->id, [
            'percentage_payment' => 40,
            'percentage_paid_history' => 100,
            'import_payment' => 400,
            'import_paid_history' => 1000,
        ]);

        Passport::actingAs($this->userOf($company));

        $this->postJson('/api/receipt/' . $second->id . '/anular')->assertOk();

        $remaining = SaleInvoices::find($invoice->id);

        $this->assertSame(Constantes::PARCIALMENTE_CANCELADA, (int) $remaining->status_id);

        $pivot = $first->saleInvoices()->first()->pivot;

        $this->assertSame(600.0, (float) $pivot->import_paid_history);
        $this->assertSame(60.0, (float) $pivot->percentage_paid_history);
    }

    public function test_it_rejects_a_receipt_that_was_already_cancelled(): void
    {
        $company = Company::factory()->create();
        $customer = Customer::factory()->create(['company_id' => $company->id]);
        $receipt = $this->receiptWithPayment($company, $customer);

        Passport::actingAs($this->userOf($company));

        $this->postJson('/api/receipt/' . $receipt->id . '/anular')->assertOk();
        $this->postJson('/api/receipt/' . $receipt->id . '/anular')->assertStatus(409);
    }

    public function test_it_does_not_cancel_a_receipt_of_another_company(): void
    {
        $mine = Company::factory()->create();
        $other = Company::factory()->create();

        $foreign = $this->receiptWithPayment($other, Customer::factory()->create(['company_id' => $other->id]));

        Passport::actingAs($this->userOf($mine));

        $this->postJson('/api/receipt/' . $foreign->id . '/anular')->assertStatus(404);

        $this->assertNull(Receipt::find($foreign->id)->cancelled_at);
    }
}
