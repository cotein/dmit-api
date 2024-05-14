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

class SaleInvoiceRepository
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

        if ($request->has('invoice_id')) {
            $query->where('id', $request->invoice_id);
        }

        if ($request->has('from') && $request->has('to')) {
            $startDate = Carbon::createFromFormat('Y-m-d', $request->from);
            $endDate = Carbon::createFromFormat('Y-m-d', $request->to);
            $query->whereBetween('cbte_fch', [$startDate, $endDate]);
        }
    }

    public function find(Request $request)
    {
        $query = SaleInvoices::query();

        // Filtrar por compañía
        $query->where('company_id', $request->company_id);

        // Aplicar filtros opcionales
        $this->applyOptionalFilters($query, $request);

        // Ordenar resultados
        $query->orderBy('cbte_fch', 'desc')->orderBy('cbte_desde', 'desc');

        // Paginar resultados (excepto para impresión)
        if (!$request->has('print') || $request->get('print') !== 'yes') {
            return $query->paginate($request->per_page);
        }

        // Devolver todos los resultados para impresión
        return $query->get();
    }

    private function status($data): int
    {
        $saleCondition_id = (int) $data['saleCondition'];

        $saleCondition = SaleCondition::find($saleCondition_id);

        if ($saleCondition->days === 0) {
            return Constantes::CANCELADA;
        }

        return Constantes::ADEUDADA;
    }

    public function store($data): SaleInvoices
    {
        //try {
        $voucher = $data['FeCabReq']['CbteTipo'];

        if ($voucher < 10) {
            $voucher = '00' . $voucher;
        } elseif ($voucher < 100) {
            $voucher = '0' . $voucher;
        } else {
            $voucher = (string) $voucher;
        }
        Log::alert($voucher);
        $voucher_id = AfipVoucher::where('afip_code', $voucher)->get()->first()->id;
        Log::alert($voucher);
        $result = json_decode(json_encode($data['result']), true);

        // Crear la nueva factura
        $invoice = new SaleInvoices();
        $invoice->company_id = $data['company_id'];
        $invoice->customer_id = array_key_exists('value', $data['customer']) ? $data['customer']['value'] : $data['customer']['id'];
        $invoice->voucher_id = $voucher_id;
        $invoice->pto_vta = $data['FeCabReq']['PtoVta'];
        $invoice->cbte_desde = $data['FECAEDetRequest']['CbteDesde'];
        $invoice->cbte_hasta = $data['FECAEDetRequest']['CbteDesde'];
        $invoice->cbte_fch = Carbon::parse($data['FECAEDetRequest']['CbteFch']);
        $invoice->cae = $result['FECAESolicitarResult']['FeDetResp']['FECAEDetResponse'][0]['CAE'];
        $invoice->cae_fch_vto = Carbon::parse($result['FECAESolicitarResult']['FeDetResp']['FECAEDetResponse'][0]['CAEFchVto']);
        $invoice->user_id = $data['user_id'];
        $invoice->afip_data = collect($result)->toJson();
        $invoice->vto_payment = null;
        $invoice->commercial_reference = null;
        $invoice->payment_type_id = $data['paymentType'];
        $invoice->sales_condition_id = $data['saleCondition'];
        $invoice->status_id = $this->status($data);
        $invoice->parent_id = array_key_exists('parent', $data) ? $data['parent'] : null;
        $invoice->fch_serv_desde = (!empty($data['FECAEDetRequest']['FchServDesde'])) ? Carbon::parse($data['FECAEDetRequest']['FchServDesde']) : null;
        $invoice->fch_serv_hasta = (!empty($data['FECAEDetRequest']['FchServHasta'])) ? Carbon::parse($data['FECAEDetRequest']['FchServHasta']) : null;
        $invoice->fch_vto_pago = (!empty($data['FECAEDetRequest']['FchVtoPago'])) ? Carbon::parse($data['FECAEDetRequest']['FchVtoPago']) : null;
        $invoice->save();

        if (array_key_exists('parent', $data) && !isNull($data['parent'])) {
            $si = SaleInvoices::find((int) $data['parent']);
            $si->parent_id = $invoice->id;
            $si->save();
        }
        // Crear items de la factura
        $this->createInvoiceItems($invoice, $data['products']);

        // Guardar comentario de la factura (si existe)
        if (isset($data['comments'])) {
            $this->createInvoiceComment($invoice, $data['comments']);
        }

        $this->customer_cuenta_corriente_repository->store($invoice);

        return $invoice;
        /* } catch (\Exception $e) {

            Log::error($e->getMessage());
            throw new \Exception('Error al guardar la factura.');
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
            $item->discount_percentage = 0;
            $item->discount_import = 0;
            $item->total = $product['total'];
            $item->obs = null;
            $item->unit_price = array_key_exists('unit', $product) ? $product['unit'] : $product['unit_price'];
            //$item->unit_price = $product['unit'];
            $item->price_list_id = array_key_exists('priceList', $product) ? $product['priceList']['id'] : null;
            $item->voucher_id = $invoice->voucher_id;
            $item->aditional_percentage = array_key_exists('aditional', $product) ? $product['aditional']['percentage'] : 0;
            $item->aditional_value = array_key_exists('aditional', $product) ? ($product['unit'] * $product['quantity']) - ($product['price_base'] * $product['quantity']) : 0;
            $item->save();

            unset($item);
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
