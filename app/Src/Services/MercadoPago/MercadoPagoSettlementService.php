<?php

namespace App\Src\Services\MercadoPago;

use App\Models\MercadoPagoCharge;
use App\Models\MercadoPagoPayment;
use App\Models\PaymentType;
use App\Models\SaleInvoices;
use App\Models\User;
use App\Src\Repositories\ReceiptRepository;
use App\Src\Scopes\CompanyScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Concilia lo que dice Mercado Pago con lo que dice DMIT.
 *
 * Es la única puerta por la que un pago de MP mueve datos del negocio, y la usan
 * los tres caminos: webhook, conciliación por comando y (a futuro) el QR del
 * mostrador. Es idempotente: el mismo pago procesado dos veces no imputa dos veces.
 */
class MercadoPagoSettlementService
{
    public function __construct(private ReceiptRepository $receiptRepository)
    {
    }

    /**
     * Registra (o actualiza) un pago de MP y, si corresponde, lo imputa.
     *
     * @param  array  $mp  el pago tal cual lo devuelve la API de Mercado Pago
     */
    public function applyPayment(array $mp): ?MercadoPagoPayment
    {
        $mpPaymentId = isset($mp['id']) ? (string) $mp['id'] : null;

        if ($mpPaymentId === null) {
            return null;
        }

        $charge = $this->resolveCharge($mp);

        if ($charge === null) {
            // Pago sin cobro nuestro (por ejemplo, cobrado directo en el panel de MP).
            Log::warning('Mercado Pago: pago sin cobro asociado', [
                'mp_payment_id' => $mpPaymentId,
                'external_reference' => $mp['external_reference'] ?? null,
            ]);

            return null;
        }

        return DB::transaction(function () use ($mp, $mpPaymentId, $charge) {
            /** @var MercadoPagoCharge $charge */
            $charge = MercadoPagoCharge::withoutGlobalScope(CompanyScope::class)
                ->lockForUpdate()
                ->find($charge->id);

            $payment = MercadoPagoPayment::withoutGlobalScope(CompanyScope::class)
                ->firstOrNew(['mp_payment_id' => $mpPaymentId]);

            $payment->fill([
                'company_id' => $charge->company_id,
                'charge_id' => $charge->id,
                'mp_merchant_order_id' => isset($mp['order']['id']) ? (string) $mp['order']['id'] : null,
                'mp_preapproval_id' => $mp['preapproval_id'] ?? null,
                'status' => $mp['status'] ?? 'unknown',
                'status_detail' => $mp['status_detail'] ?? null,
                'amount' => $mp['transaction_amount'] ?? 0,
                'fee_amount' => $mp['fee_details'][0]['amount'] ?? null,
                'net_amount' => $mp['transaction_details']['net_received_amount'] ?? null,
                'payment_method_id' => $mp['payment_method_id'] ?? null,
                'payment_type' => $mp['payment_type_id'] ?? null,
                'installments' => $mp['installments'] ?? null,
                'payer_email' => $mp['payer']['email'] ?? null,
                'payer_id' => isset($mp['payer']['id']) ? (string) $mp['payer']['id'] : null,
                'external_reference' => $mp['external_reference'] ?? $charge->external_reference,
                'raw' => $mp,
                'approved_at' => ($mp['status'] ?? null) === 'approved' ? ($mp['date_approved'] ?? now()) : null,
                'refunded_at' => in_array($mp['status'] ?? null, ['refunded', 'charged_back'], true) ? now() : null,
            ]);
            $payment->save();

            $charge->update([
                'status' => $this->mapStatus($mp['status'] ?? null),
                'status_detail' => $mp['status_detail'] ?? null,
                'paid_at' => ($mp['status'] ?? null) === 'approved' ? ($charge->paid_at ?? now()) : $charge->paid_at,
            ]);

            if (($mp['status'] ?? null) === 'approved') {
                $this->settle($charge->refresh(), $payment);
            }

            // Devolución o contracargo: si el cobro YA se había imputado, el
            // comprobante sigue marcado como pagado. NO se revierte solo (eso es
            // una decisión de negocio), pero se marca para que la pantalla lo
            // avise y alguien lo resuelva.
            if (in_array($mp['status'] ?? null, ['refunded', 'charged_back'], true)) {
                $this->flagRefundAfterSettlement($charge->refresh());
            }

            return $payment;
        });
    }

    /**
     * Marca el cobro para revisión cuando una devolución cae sobre algo ya
     * imputado. Idempotente: se marca una sola vez.
     */
    private function flagRefundAfterSettlement(MercadoPagoCharge $charge): void
    {
        $metadata = $charge->metadata ?? [];
        $recibo = $metadata['settlement']['receipt_id'] ?? null;

        if (empty($recibo) || ! empty($metadata['needs_review'])) {
            return;
        }

        $metadata['needs_review'] = [
            'reason' => 'refund_after_settlement',
            'note' => 'Se devolvió un pago que ya estaba imputado en el recibo #' . $recibo . '. Hay que revisarlo.',
            'at' => now()->toIso8601String(),
        ];

        $charge->update(['metadata' => $metadata]);

        Log::warning('Mercado Pago: devolución sobre un cobro ya imputado', [
            'charge_id' => $charge->id,
            'receipt_id' => $recibo,
            'mp_payment_id' => $charge->payments()->latest('id')->value('mp_payment_id'),
        ]);
    }

    /**
     * Imputa el cobro a lo que le dio origen. Se salta si ya se imputó (guarda en
     * metadata) para que un webhook repetido no cree un segundo recibo.
     */
    public function settle(MercadoPagoCharge $charge, MercadoPagoPayment $payment): void
    {
        $metadata = $charge->metadata ?? [];

        if (! empty($metadata['settlement']['receipt_id'])) {
            return;
        }

        if (in_array($charge->concept, [MercadoPagoCharge::CONCEPT_INVOICE, MercadoPagoCharge::CONCEPT_CURRENT_ACCOUNT], true)) {
            $this->impute($charge, $payment);

            return;
        }

        // Cobro manual o de mostrador: no hay nada que imputar, sólo queda registrado.
        $this->markSettled($charge, null);
    }

    private function impute(MercadoPagoCharge $charge, MercadoPagoPayment $payment): void
    {
        $paymentTypeId = $this->paymentTypeIdFor($charge->company_id);

        if ($paymentTypeId === null) {
            // Sin tipo de pago no se puede armar el recibo: se registra el pago y
            // se deja rastro para que el usuario lo impute a mano.
            Log::warning('Mercado Pago: no hay tipo de pago para imputar (company ' . $charge->company_id . ')');
            $this->markSettled($charge, null, 'Falta un tipo de pago para imputar automáticamente.');

            return;
        }

        $documento = [
            'payment_type_id' => $paymentTypeId,
            'import' => (float) $payment->amount,
            'number' => (string) $payment->mp_payment_id,
            'date' => now()->toDateString(),
            'comments' => 'Cobro online Mercado Pago #' . $payment->mp_payment_id,
        ];

        $payload = [
            'company_id' => $charge->company_id,
            'customer_id' => $charge->customer_id,
            'documentsCancelation' => [$documento],
            'saldo' => 0,
        ];

        if ($charge->concept === MercadoPagoCharge::CONCEPT_INVOICE && $charge->sale_invoice_id) {
            $invoice = SaleInvoices::find($charge->sale_invoice_id);

            if ($invoice) {
                $payload['invoicesToCancel'] = [[
                    'key' => $invoice->id,
                    'importe' => (float) $invoice->totalInvoiced(),
                    'toPayNow' => (float) $payment->amount,
                ]];
            }
        } else {
            // Pago a cuenta: queda a favor del cliente (saldo negativo = a favor).
            $payload['saldo'] = -1 * (float) $payment->amount;
        }

        // ReceiptRepository::createReceipt toma el user_id de auth(): se le da el
        // usuario que generó el cobro para que el recibo quede con dueño. En el
        // webhook no hay sesión, por eso se fija acá y no en el controlador.
        $this->actingAsChargeOwner($charge);

        $receipt = $this->receiptRepository->createReceipt(new \Illuminate\Http\Request($payload));

        $this->markSettled($charge, $receipt?->id);
    }

    /**
     * Fija el usuario autenticado que va a quedar en el recibo. El middleware
     * auth:api hace esto mismo en un request normal; en un job hay que hacerlo a mano.
     */
    private function actingAsChargeOwner(MercadoPagoCharge $charge): void
    {
        if (Auth::guard('api')->check()) {
            return;
        }

        $userId = $charge->created_by;

        if ($userId === null) {
            $userId = User::whereHas('companies', function ($query) use ($charge) {
                $query->where('companies.id', $charge->company_id);
            })->orderBy('id')->value('id');
        }

        if ($userId !== null) {
            Auth::shouldUse('api');
            Auth::guard('api')->setUser(User::find($userId));
        }
    }

    private function markSettled(MercadoPagoCharge $charge, ?int $receiptId, ?string $nota = null): void
    {
        $metadata = $charge->metadata ?? [];
        $metadata['settlement'] = array_filter([
            'receipt_id' => $receiptId,
            'at' => now()->toIso8601String(),
            'note' => $nota,
        ]);

        $charge->update(['metadata' => $metadata]);
    }

    /**
     * Tipo de pago "Mercado Pago" de la empresa. Si no existe se crea: es un dato
     * de configuración del negocio, no del cobro, y sin él no se puede imputar.
     */
    private function paymentTypeIdFor(int $companyId): ?int
    {
        $existente = PaymentType::where('company_id', $companyId)
            ->whereRaw('LOWER(name) = ?', ['mercado pago'])
            ->value('id');

        if ($existente) {
            return (int) $existente;
        }

        try {
            // OJO: PaymentType no declara $fillable, así que create() tira
            // MassAssignmentException. Se arma por asignación directa para no
            // tocar el modelo del repo (y para que el alta no dependa de eso).
            $nuevo = new PaymentType;
            $nuevo->company_id = $companyId;
            $nuevo->name = 'Mercado Pago';
            $nuevo->percentage = 0;
            $nuevo->active = true;
            $nuevo->save();

            return (int) $nuevo->id;
        } catch (\Throwable $e) {
            Log::error('Mercado Pago: no se pudo crear el tipo de pago para imputar', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function resolveCharge(array $mp): ?MercadoPagoCharge
    {
        $referencia = $mp['external_reference'] ?? null;

        if ($referencia) {
            $charge = MercadoPagoCharge::withoutGlobalScope(CompanyScope::class)
                ->where('external_reference', $referencia)
                ->first();

            if ($charge) {
                return $charge;
            }
        }

        $preferenceId = $mp['metadata']['preference_id'] ?? null;

        if ($preferenceId) {
            return MercadoPagoCharge::withoutGlobalScope(CompanyScope::class)
                ->where('preference_id', $preferenceId)
                ->first();
        }

        return null;
    }

    /**
     * El estado que ve el usuario es el de MP, mapeado a los nuestros.
     */
    private function mapStatus(?string $mpStatus): string
    {
        return match ($mpStatus) {
            'approved' => MercadoPagoCharge::STATUS_APPROVED,
            'pending', 'authorized' => MercadoPagoCharge::STATUS_PENDING,
            'in_process', 'in_mediation' => MercadoPagoCharge::STATUS_IN_PROCESS,
            'rejected' => MercadoPagoCharge::STATUS_REJECTED,
            'cancelled' => MercadoPagoCharge::STATUS_CANCELLED,
            'refunded', 'charged_back' => MercadoPagoCharge::STATUS_REFUNDED,
            default => MercadoPagoCharge::STATUS_PENDING,
        };
    }
}
