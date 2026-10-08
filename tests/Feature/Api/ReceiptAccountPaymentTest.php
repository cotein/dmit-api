<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Receipt;
use App\Models\SaleInvoices;
use App\Models\User;
use App\Src\Constantes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Alta de recibos: con comprobantes imputados como siempre, y sin comprobantes
 * como PAGO A CUENTA (saldo a favor del cliente en receipts.saldo).
 */
class ReceiptAccountPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function userOf(Company $company): User
    {
        $user = User::factory()->create();
        $user->companies()->attach($company->id);

        return $user;
    }

    private function document(array $overrides = []): array
    {
        return array_merge([
            'payment_type_id' => 1,
            'import' => 500,
            'imputation_date' => now()->toDateString(),
            'number' => 'TR-1',
            'comments' => 'Pago a cuenta',
            'cbu_id' => null,
            'bank' => null,
            'chequeDate' => null,
            'chequeExpirate' => null,
            'chequeOwner' => null,
        ], $overrides);
    }

    public function test_it_saves_a_payment_without_invoices_as_an_account_payment(): void
    {
        $company = Company::factory()->create();
        $customer = Customer::factory()->create(['company_id' => $company->id]);

        Passport::actingAs($this->userOf($company));

        $this->postJson('/api/receipt', [
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'saldo' => -500,
            'documentsCancelation' => [$this->document()],
        ])->assertCreated();

        $receipt = Receipt::latest('id')->first();

        $this->assertSame($customer->id, (int) $receipt->customer_id);
        $this->assertSame($company->id, (int) $receipt->company_id);
        $this->assertSame(500.0, (float) $receipt->total);
        $this->assertSame(-500.0, (float) $receipt->saldo);

        // Sin imputacion: el pago queda a cuenta del cliente.
        $this->assertSame(0, $receipt->saleInvoices()->count());

        $this->assertDatabaseHas('receipt_payments', [
            'receipt_id' => $receipt->id,
            'number' => 'TR-1',
            'total' => 500,
        ]);
    }

    public function test_an_account_payment_needs_customer_and_company(): void
    {
        $company = Company::factory()->create();

        Passport::actingAs($this->userOf($company));

        $this->postJson('/api/receipt', [
            'documentsCancelation' => [$this->document()],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('customer_id');
    }

    public function test_a_receipt_without_any_payment_is_rejected(): void
    {
        $company = Company::factory()->create();
        $customer = Customer::factory()->create(['company_id' => $company->id]);

        Passport::actingAs($this->userOf($company));

        $this->postJson('/api/receipt', [
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'documentsCancelation' => [],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('documentsCancelation');
    }

    public function test_it_still_imputes_the_invoice_and_cancels_it(): void
    {
        $company = Company::factory()->create();
        $customer = Customer::factory()->create(['company_id' => $company->id]);
        $invoice = SaleInvoices::factory()->create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'status_id' => Constantes::ADEUDADA,
        ]);

        Passport::actingAs($this->userOf($company));

        $this->postJson('/api/receipt', [
            'invoicesToCancel' => [
                ['key' => $invoice->id, 'importe' => 1000, 'toPayNow' => 1000],
            ],
            'documentsCancelation' => [$this->document(['import' => 1000])],
            'saldo' => 0,
        ])->assertCreated();

        $receipt = Receipt::latest('id')->first();

        $this->assertSame(1, $receipt->saleInvoices()->count());
        $this->assertSame(Constantes::CANCELADA, (int) SaleInvoices::find($invoice->id)->status_id);
    }

    public function test_it_rejects_an_invoice_of_another_company(): void
    {
        $mine = Company::factory()->create();
        $other = Company::factory()->create();
        $foreignInvoice = SaleInvoices::factory()->create([
            'company_id' => $other->id,
            'customer_id' => Customer::factory()->create(['company_id' => $other->id])->id,
        ]);

        Passport::actingAs($this->userOf($mine));

        $this->postJson('/api/receipt', [
            'invoicesToCancel' => [
                ['key' => $foreignInvoice->id, 'importe' => 1000, 'toPayNow' => 100],
            ],
            'documentsCancelation' => [$this->document(['import' => 100])],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('invoicesToCancel.0.key');
    }
}
