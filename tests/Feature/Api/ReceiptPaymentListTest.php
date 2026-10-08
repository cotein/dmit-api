<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Receipt;
use App\Models\ReceiptPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Listado de pagos recibidos: una fila por pago (receipt_payments), con los
 * filtros de fecha, cliente, tipo de pago, importe y estado derivado.
 *
 * Ojo: la tabla receipts NO tiene status_id. El estado de un pago sale del
 * recibo (cancelled_at) y de la imputacion sobre los comprobantes.
 */
class ReceiptPaymentListTest extends TestCase
{
    use RefreshDatabase;

    private function userOf(Company $company): User
    {
        $user = User::factory()->create();
        $user->companies()->attach($company->id);

        return $user;
    }

    private function receiptOf(Company $company, Customer $customer, array $attributes = []): Receipt
    {
        $receipt = new Receipt();
        $receipt->company_id = $company->id;
        $receipt->customer_id = $customer->id;
        $receipt->total = 0;
        $receipt->saldo = 0;

        foreach ($attributes as $key => $value) {
            $receipt->{$key} = $value;
        }

        $receipt->save();

        return $receipt;
    }

    private function paymentOf(Receipt $receipt, array $attributes = []): ReceiptPayment
    {
        $payment = new ReceiptPayment();
        $payment->receipt_id = $receipt->id;
        $payment->date = now()->toDateString();
        $payment->total = 100;
        $payment->description = 'Pago de prueba';

        foreach ($attributes as $key => $value) {
            $payment->{$key} = $value;
        }

        $payment->save();

        return $payment;
    }

    public function test_it_lists_one_row_per_payment_of_the_company(): void
    {
        $mine = Company::factory()->create();
        $other = Company::factory()->create();

        $receipt = $this->receiptOf($mine, Customer::factory()->create(['company_id' => $mine->id]));
        $this->paymentOf($receipt, ['total' => 500]);
        $this->paymentOf($receipt, ['total' => 250]);

        $foreign = $this->receiptOf($other, Customer::factory()->create(['company_id' => $other->id]));
        $this->paymentOf($foreign, ['total' => 999]);

        Passport::actingAs($this->userOf($mine));

        $response = $this->getJson('/api/receipt-payments?company_id=' . $mine->id);

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('pagination.total', 2);

        $this->assertEqualsCanonicalizing([500, 250], array_column($response->json('data'), 'amount'));
    }

    public function test_it_rejects_a_request_without_company(): void
    {
        $company = Company::factory()->create();

        Passport::actingAs($this->userOf($company));

        $this->getJson('/api/receipt-payments')
            ->assertStatus(422)
            ->assertJsonValidationErrors('company_id');
    }

    public function test_it_filters_by_payment_type_and_amount_range(): void
    {
        $company = Company::factory()->create();
        $receipt = $this->receiptOf($company, Customer::factory()->create(['company_id' => $company->id]));

        $this->paymentOf($receipt, ['total' => 100, 'payment_type_id' => 1]);
        $this->paymentOf($receipt, ['total' => 5000, 'payment_type_id' => 2]);

        Passport::actingAs($this->userOf($company));

        $this->getJson('/api/receipt-payments?company_id=' . $company->id . '&payment_type_id=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.amount', 5000);

        $this->getJson('/api/receipt-payments?company_id=' . $company->id . '&amount_from=200&amount_to=6000')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.amount', 5000);
    }

    public function test_it_derives_a_cuenta_and_anulado(): void
    {
        $company = Company::factory()->create();
        $customer = Customer::factory()->create(['company_id' => $company->id]);

        $account = $this->receiptOf($company, $customer, ['saldo' => 300]);
        $this->paymentOf($account, ['total' => 300]);

        $cancelled = $this->receiptOf($company, $customer, [
            'cancelled_at' => now(),
            'cancel_reason' => 'Se cargó dos veces',
        ]);
        $this->paymentOf($cancelled, ['total' => 800]);

        Passport::actingAs($this->userOf($company));

        $this->getJson('/api/receipt-payments?company_id=' . $company->id . '&status=a_cuenta')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'a_cuenta')
            ->assertJsonPath('data.0.receipt_balance', 300.0);

        $this->getJson('/api/receipt-payments?company_id=' . $company->id . '&status=anulado')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'anulado')
            ->assertJsonPath('data.0.cancel_reason', 'Se cargó dos veces');
    }
}
