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
        if ($request->has('status_id')) {
            $query->where('status_id', $request->status_id);
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

        $si_id = (int) $request->invoicesToCancel[0]['key'];

        DB::transaction(function () use ($request, $si_id, &$result) {

            $si = SaleInvoices::findOrFail($si_id);

            try {
                // Crear el recibo
                $receipt = new Receipt;
                $receipt->customer_id = $si->customer_id;
                $receipt->company_id = $si->company_id;
                $receipt->user_id = auth()->user()->id;
                $receipt->total = collect($request->documentsCancelation)->sum('import');
                $receipt->saldo =   $request->saldo; // si el saldo es negativo, es a favor del cliente
                $receipt->save();

                collect($request->invoicesToCancel)->each(function ($invoice) use ($receipt) {
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
                collect($request->documentsCancelation)->each(function ($document) use ($receipt) {
                    $receipt_payment = new ReceiptPayment;
                    $receipt_payment->receipt_id = $receipt->id;
                    $receipt_payment->payment_type_id = $document['payment_type_id'];
                    $receipt_payment->number = $document['number'];
                    $receipt_payment->cbu_id = (is_null($document['cbu_id'])) ? 0 : $document['cbu_id'];
                    $receipt_payment->date = new Date($document['imputation_date']);
                    $receipt_payment->description = $document['comments'];
                    $receipt_payment->total = $document['import'];
                    $receipt_payment->bank_id = $document['bank'];
                    $receipt_payment->cheque_date = $document['chequeDate'];
                    $receipt_payment->cheque_expirate = $document['chequeExpirate'];
                    $receipt_payment->cheque_owner = $document['chequeOwner'];
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
