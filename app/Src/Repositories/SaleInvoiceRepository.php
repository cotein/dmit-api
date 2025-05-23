<?php

namespace App\Src\Repositories;

use Carbon\Carbon;
use App\Src\Constantes;
use App\Models\Customer;
use App\Models\AfipVoucher;
use App\Models\SaleInvoices;
use Illuminate\Http\Request;
use App\Models\SaleCondition;
use App\Models\SaleInvoicesItem;
use Illuminate\Support\Facades\Log;
use App\Models\SaleInvoicesComments;

use function PHPUnit\Framework\isNull;
use App\Src\Repositories\CustomerCuentaCorrienteRepository;
use App\Src\Strategy\InvoiceStatus\InvoiceStatusContext;
use App\Src\Strategy\InvoiceStatus\InvoiceStatusDefaultStrategy;
use App\Src\Strategy\InvoiceStatus\InvoiceStatusNotaCreditoStrategy;

class SaleInvoiceRepository
{

    protected $customer_cuenta_corriente_repository;

    private $voucherIdsToSum = [1, 2, 6, 7, 11, 12, 92, 93, 95, 96, 98, 99];

    public function __construct(CustomerCuentaCorrienteRepository $customer_cuenta_corriente_repository)
    {
        $this->customer_cuenta_corriente_repository = $customer_cuenta_corriente_repository;
    }

    private function getTotalIncomeForMonth(int $month = null, int $year = null, int $companyId = null): float
    {

        $month = $month ?? now()->month;
        $year = $year ?? now()->year;

        $query = SaleInvoices::whereMonth('cbte_fch', $month)
            ->whereYear('cbte_fch', $year);

        if (!is_null($companyId)) {
            $query->where('company_id', $companyId);
        }

        return $query->with(['items' => function ($query) {
            $query->selectRaw('sale_invoice_id, SUM(total) as total_amount')
                ->whereIn('voucher_id', $this->voucherIdsToSum)
                ->groupBy('sale_invoice_id');
        }])
            ->get()
            ->sum(function ($invoice) {
                return $invoice->items->first()->total_amount ?? 0;
            });
    }

    public function getDailySalesReport(int $companyId = null): array
    {
        $currentMonth = now()->month;
        $currentYear = now()->year;

        $dailySales = collect([]);

        for ($day = 1; $day <= now()->daysInMonth; $day++) {
            $date = Carbon::create($currentYear, $currentMonth, $day);

            $dailySales->push([
                'date' => $date->format('d-m-Y'),
                'sales' => SaleInvoices::whereDate('cbte_fch', $date)
                    ->when($companyId, function ($query) use ($companyId) {
                        return $query->where('company_id', $companyId);
                    })
                    ->with(['items' => function ($query) {
                        $query->selectRaw('sale_invoice_id, SUM(total) as total_amount')
                            ->whereIn('voucher_id', $this->voucherIdsToSum)
                            ->groupBy('sale_invoice_id');
                    }])
                    ->get()
                    ->sum(function ($invoice) {
                        return $invoice->items->first()->total_amount ?? 0;
                    }),
                //'orders' => SaleInvoices::whereDate('cbte_fch', $date)->count(),
            ]);
        }

        $labels = $dailySales->pluck('date')->toArray();
        $sales = $dailySales->pluck('sales')->toArray();
        //$orders = $dailySales->pluck('orders')->toArray();

        $totalSales = array_sum($sales);

        // Calcular ingresos del mes anterior
        $previousMonth = now()->subMonth();
        $previousMonthSales = $this->getTotalIncomeForMonth($previousMonth->month, $previousMonth->year, $companyId);

        $growthRate = $previousMonthSales > 0 ? (($totalSales - $previousMonthSales) / $previousMonthSales) * 100 : 0;
        $growthStatus = $growthRate > 0 ? 'up' : ($growthRate < 0 ? 'down' : 'stable');

        return [
            'title' => 'Ventas diarias - ' . now()->locale('es')->monthName . ' ' . now()->year,
            'labels' => $labels,
            //'orders' => $orders,
            'sales' => $sales,
            'total' => number_format($totalSales, 2),
            'growthRate' => number_format($growthRate, 2),
            'growthStatus' => $growthStatus,
        ];
    }

    private function applyOptionalFilters($query, Request $request): void
    {
        if ($request->has('status_id')) {
            $query->where('status_id', $request->status_id);
        }

        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->has('invoice_id')) {
            $query->where('id', $request->invoice_id);
        }

        if ($request->has('getPaymentOnReceipt')) {
            $query->whereIn('status_id', [Constantes::ADEUDADA, Constantes::PARCIALMENTE_CANCELADA]);
        }

        if ($request->has('from') && $request->has('to')) {
            $from = Carbon::createFromFormat('Y-m-d', $request->from);
            $to = Carbon::createFromFormat('Y-m-d', $request->to);
            $query->whereBetween('cbte_fch', [$request->from, $request->to]);
        }
    }

    public function find(Request $request)
    {
        $query = SaleInvoices::query();

        // Filtrar por compañía
        $query->where('company_id', (int) $request->company_id);

        if ($request->has('getLastMonthInvoiced')) {

            $totalIncomeThisMonth = $this->getTotalIncomeForMonth(now()->month, now()->year, $request->company_id);

            $previousMonth = now()->subMonth();

            $totalIncomeLastMonth = $this->getTotalIncomeForMonth($previousMonth->month, $previousMonth->year, $request->company_id);

            return [
                'totalIncomeThisMonth' => $totalIncomeThisMonth,
                'totalIncomeLastMonth' => $totalIncomeLastMonth
            ];
        }

        if ($request->has('getDailySalesReport')) {

            return  $this->getDailySalesReport($request->company_id);
        }

        // Aplicar filtros opcionales
        $this->applyOptionalFilters($query, $request);

        // Ordenar resultados
        $query->orderBy('cbte_fch', 'desc')->orderBy('cbte_desde', 'desc');

        // Paginar resultados (excepto para impresión)
        if ($request->has('print') && $request->get('print') === 'no') {
            return $query->paginate($request->per_page);
        }

        if ($request->has('comments')) {
            return $query->whereHas('comments')->paginate($request->per_page);
        }

        // Devolver todos los resultados para impresión
        return $query->get();
    }

    private function status($data, $invoice): void
    {
        $strategy = null;

        if (in_array($data['FeCabReq']['CbteTipo'], Constantes::IS_FACTURA_AFIP_CODE)) {
            $strategy = new InvoiceStatusDefaultStrategy();
        }
        if (in_array($data['FeCabReq']['CbteTipo'], Constantes::IS_NOTA_CREDITO_AFIP_CODE)) {
            $strategy = new InvoiceStatusNotaCreditoStrategy();
        }

        $context = new InvoiceStatusContext($strategy);

        $status = $context->executeStrategy($data, $invoice);
        $invoice->status_id = $status;
        $invoice->save();
    }

    public function store($data): SaleInvoices
    {
        //try {
        $saleCondition = SaleCondition::find($data['saleCondition']);

        $voucher = (int) $data['FeCabReq']['CbteTipo'];

        if ($voucher < 10) {
            $voucher = '00' . $voucher;
        } elseif ($voucher < 100) {
            $voucher = '0' . $voucher;
        } else {
            $voucher = (string) $voucher;
        }

        $voucher_id = AfipVoucher::where('afip_code', $voucher)->get()->first()->id;

        $result = json_decode(json_encode($data['result']), true);

        // Crear la nueva factura
        $invoice = new SaleInvoices();
        $invoice->company_id = $data['company_id'];
        $invoice->customer_id = array_key_exists('value', $data['customer']) ? $data['customer']['value'] : $data['customer']['id'];
        $invoice->voucher_id = $voucher_id;
        $invoice->pto_vta = $data['FeCabReq']['PtoVta'];
        $invoice->cbte_desde = $result['FECAESolicitarResult']['FeDetResp']['FECAEDetResponse'][0]['CbteDesde'];
        $invoice->cbte_hasta = $result['FECAESolicitarResult']['FeDetResp']['FECAEDetResponse'][0]['CbteHasta'];
        $invoice->cbte_fch = Carbon::parse($data['FECAEDetRequest']['CbteFch']);
        $invoice->cae = $result['FECAESolicitarResult']['FeDetResp']['FECAEDetResponse'][0]['CAE'];
        $invoice->cae_fch_vto = Carbon::parse($result['FECAESolicitarResult']['FeDetResp']['FECAEDetResponse'][0]['CAEFchVto']);
        $invoice->user_id = $data['user_id'];
        $invoice->afip_data = collect($result)->toJson();
        $invoice->vto_payment = null;
        $invoice->commercial_reference = null;
        $invoice->payment_type_id = $data['paymentType'];
        $invoice->sales_condition_id = $data['saleCondition'];
        //$invoice->status_id = $this->status($data);
        $invoice->parent_id = array_key_exists('parent', $data) ? $data['parent'] : null;
        $invoice->fch_serv_desde = (!empty($data['FECAEDetRequest']['FchServDesde'])) ? Carbon::parse($data['FECAEDetRequest']['FchServDesde']) : null;
        $invoice->fch_serv_hasta = (!empty($data['FECAEDetRequest']['FchServHasta'])) ? Carbon::parse($data['FECAEDetRequest']['FchServHasta']) : null;
        $invoice->fch_vto_pago = (!empty($data['FECAEDetRequest']['FchVtoPago'])) ? Carbon::parse($data['FECAEDetRequest']['FchVtoPago']) : Carbon::parse($data['FECAEDetRequest']['CbteFch'])->addDays($saleCondition->days);
        $invoice->save();

        if (array_key_exists('parent', $data) && !isNull($data['parent'])) {
            $si = SaleInvoices::find((int) $data['parent']);
            $si->parent_id = $invoice->id;
            $si->save();
        }
        // Crear items de la factura
        $this->createInvoiceItems($invoice, $data['products']);

        $this->status($data, $invoice);

        // Guardar comentario de la factura (si existe)
        if (isset($data['comments'])) {
            $this->createInvoiceComment($invoice, $data['comments']);
        }

        $invoice->refresh();
        $this->customer_cuenta_corriente_repository->store($invoice);

        return $invoice;
        /* } catch (\Exception $e) {

            Log::error($e->getMessage());
            throw new \Exception('El comprobante se generó en AFIP y no se pudo registrar en la base de datos.');
        } */
    }

    private function createInvoiceItems(SaleInvoices $invoice, array $products): void
    {
        collect($products)->map(function ($product) use ($invoice) {

            $item = new SaleInvoicesItem();
            $item->sale_invoice_id = $invoice->id;
            $item->product_id = array_key_exists('product', $product) ? $product['product']['id'] : $product['id'];
            $item->quantity = $product['quantity'];
            $item->neto_import = array_key_exists('subtotal', $product) ? $product['subtotal'] : $product['neto_import'];
            $item->iva_import = $product['iva_import'];
            $item->iva_id = array_key_exists('iva', $product) ? $product['iva']['id'] : $product['iva_id'];
            $item->discount_percentage = array_key_exists('discount_percentage', $product) ? $product['discount_percentage'] : 0;
            $item->discount_import = array_key_exists('discount_import', $product) ? $product['discount_import'] : $product['discount'];
            $item->total = $product['total'];
            //$item->total = $product['total'] + $product['percep_iibb_import'] + $product['percep_iva_import'];
            $item->obs = null;
            $item->unit_price = array_key_exists('unit', $product) ? $product['unit'] : $product['unit_price'];
            //$item->unit_price = $product['unit'];
            $item->price_list_id = array_key_exists('priceList', $product) ? $product['priceList']['id'] : null;
            $item->voucher_id = $invoice->voucher_id;
            /* $item->aditional_percentage = array_key_exists('aditional', $product) ? $product['aditional']['percentage'] : 0;
            $item->aditional_value = array_key_exists('aditional', $product) ? ($product['unit'] * $product['quantity']) - ($product['price_base'] * $product['quantity']) : 0; */
            $item->aditional_percentage =  0;
            $item->aditional_value =  0;
            $item->percep_iibb_alicuota = $product['percep_iibb_alicuota'];
            $item->percep_iibb_import = $product['percep_iibb_import'];
            $item->percep_iva_alicuota = $product['percep_iva_alicuota'];
            $item->percep_iva_import = $product['percep_iva_import'];
            $item->save();
        });
    }

    private function createInvoiceComment(SaleInvoices $invoice, string $comment): void
    {
        $sic = new SaleInvoicesComments();
        $sic->company_id = $invoice->company_id;
        $sic->sale_invoice_id = $invoice->id;
        $sic->comment = $comment;
        $sic->save();
    }
}
