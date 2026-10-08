<?php

namespace App\Src\Repositories;

use Carbon\Carbon;
use App\Models\Receipt;
use App\Src\Constantes;
use Jenssegers\Date\Date;
use App\Models\SaleInvoices;
use Illuminate\Http\Request;
use App\Models\ReceiptPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReceiptRepository
{
    protected $customer_cuenta_corriente_repository;

    public function __construct(CustomerCuentaCorrienteRepository $customer_cuenta_corriente_repository)
    {
        $this->customer_cuenta_corriente_repository = $customer_cuenta_corriente_repository;
    }

    private function applyOptionalFilters($query, Request $request): void
    {
        // OJO: la tabla receipts NO tiene status_id. El filtro viejo
        // ($query->where('status_id', ...)) consultaba una columna inexistente:
        // si alguien mandaba el parametro, el listado fallaba con SQLSTATE 42S22.
        // El estado del recibo es derivado y ahora se filtra por cancelled_at.
        if ($request->filled('status')) {
            if ($request->status === 'anulado') {
                $query->whereNotNull('cancelled_at');
            } elseif ($request->status === 'activo') {
                $query->whereNull('cancelled_at');
            }
        }

        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->has('from') && $request->has('to')) {
            $from = Carbon::createFromFormat('Y-m-d', $request->from);
            $to = Carbon::createFromFormat('Y-m-d', $request->to);
            $query->whereBetween('created_at', [$request->from, $request->to]);
        }
    }

    public function find(Request $request)
    {
        $query = Receipt::query();

        // Filtrar por compañía
        $query->where('company_id', $request->company_id);

        // Aplicar filtros opcionales
        $this->applyOptionalFilters($query, $request);

        // Ordenar resultados
        $query->orderBy('created_at', 'desc');

        return $query->paginate($request->per_page);
    }

    public function show($id)
    {
        return Receipt::findOrFail($id);
    }

    public function store(SaleInvoices $si): void
    {
        // Verificar si sales_condition_id es Constantes::CONTADO
        if ((int) $si->sales_condition_id !== Constantes::CONTADO) {
            return;
        }

        DB::transaction(function () use ($si) {
            try {
                $receipt = new Receipt;

                $receipt->customer_id = $si->customer_id;
                $receipt->company_id = $si->company_id;
                $receipt->user_id = $si->user_id;
                $receipt->total = $si->totalInvoiced();
                $receipt->save();

                // Datos adicionales para la tabla pivot
                //se esta pagando de contado
                if ((int) $si->sales_condition_id === Constantes::CONTADO) {
                    $percentage_payment =   100;
                    $percentage_paid_history = $si->receipts->sum('pivot.percentage_payment') + $percentage_payment;
                    $pivotData = [
                        'percentage_payment' => $percentage_payment,
                        'percentage_paid_history' => $percentage_paid_history,
                        'import_payment' => $si->totalInvoiced(),
                        'import_paid_history' => $si->receipts->sum('pivot.import_payment') + $si->totalInvoiced()
                    ];

                    $receipt->invoices()->attach($si->id, $pivotData);
                }

                $receipt_payment = new ReceiptPayment;
                $receipt_payment->receipt_id = $receipt->id;
                $receipt_payment->payment_type_id = $si->payment_type_id;
                $receipt_payment->date = new Date();
                $receipt_payment->total = $receipt->total;
                $receipt_payment->save();

                $this->customer_cuenta_corriente_repository->store($receipt);
            } catch (\Exception $e) {
                // Log the exception or handle it as needed
                throw $e;
            }
        });
    }

    public function createReceipt(Request $request)
    {
        $result = null;

        $invoices = collect($request->input('invoicesToCancel') ?? []);
        $documents = collect($request->input('documentsCancelation') ?? []);

        DB::transaction(function () use ($request, $invoices, $documents, &$result) {

            // Con comprobantes, el cliente y la empresa salen del primero, que
            // es lo que hacia siempre. Sin comprobantes es un PAGO A CUENTA:
            // vienen en el request y el recibo queda sin imputacion, con el
            // saldo a favor del cliente en receipts.saldo.
            $firstInvoice = $invoices->isNotEmpty()
                ? SaleInvoices::findOrFail((int) $invoices->first()['key'])
                : null;

            $customerId = $firstInvoice ? $firstInvoice->customer_id : (int) $request->input('customer_id');
            $companyId = $firstInvoice ? $firstInvoice->company_id : (int) $request->input('company_id');

            try {
                // Crear el recibo
                $receipt = new Receipt;
                $receipt->customer_id = $customerId;
                $receipt->company_id = $companyId;
                $receipt->user_id = auth()->user()->id;
                $receipt->total = $documents->sum('import');
                $receipt->saldo = $request->input('saldo', 0); // si el saldo es negativo, es a favor del cliente
                $receipt->save();

                $invoices->each(function ($invoice) use ($receipt) {
                    if ($invoice['toPayNow'] > 0) {

                        // Datos adicionales para la tabla pivot
                        $percentage_payment = round($invoice['toPayNow'] * 100 / $invoice['importe'], 2);
                        $saleInvoice = SaleInvoices::find($invoice['key']);
                        $percentage_paid_history = $saleInvoice->receipts->sum('pivot.percentage_payment') + $percentage_payment;

                        $pivotData = [
                            'percentage_payment' => $percentage_payment,
                            'percentage_paid_history' => $percentage_paid_history,
                            'import_payment' => $invoice['toPayNow'],
                            'import_paid_history' => $saleInvoice->receipts->sum('pivot.import_payment') + $invoice['toPayNow']
                        ];

                        // Adjuntar la factura al recibo con los datos pivot
                        $receipt->invoices()->attach($invoice['key'], $pivotData);

                        if ($percentage_paid_history >= Constantes::CIENXCIEN) {
                            $saleInvoice->status_id = Constantes::CANCELADA;
                            $saleInvoice->save();
                        } elseif ($percentage_paid_history > 0 && $percentage_paid_history < Constantes::CIENXCIEN) {
                            $saleInvoice->status_id = Constantes::PARCIALMENTE_CANCELADA;
                            $saleInvoice->save();
                        }
                    }
                });

                // Persistir el pago
                $documents->each(function ($document) use ($receipt) {
                    $receipt_payment = new ReceiptPayment;
                    $receipt_payment->receipt_id = $receipt->id;
                    $receipt_payment->payment_type_id = $document['payment_type_id'];
                    $receipt_payment->number = $document['number'] ?? null;
                    $receipt_payment->cbu_id = empty($document['cbu_id']) ? 0 : $document['cbu_id'];
                    // La fecha de imputacion no es obligatoria en la pantalla
                    // actual: sin fecha, el pago queda con la fecha del dia.
                    $receipt_payment->date = new Date(! empty($document['imputation_date']) ? $document['imputation_date'] : null);
                    $receipt_payment->description = $document['comments'] ?? null;
                    $receipt_payment->total = $document['import'];
                    $receipt_payment->bank_id = $document['bank'] ?? null;
                    $receipt_payment->cheque_date = $document['chequeDate'] ?? null;
                    $receipt_payment->cheque_expirate = $document['chequeExpirate'] ?? null;
                    $receipt_payment->cheque_owner = $document['chequeOwner'] ?? null;
                    $receipt_payment->save();
                });

                $result = $receipt;
            } catch (\Exception $e) {
                Log::error($e->getMessage());
                throw $e;
            }
        });

        return $result;
    }
}
