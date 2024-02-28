<?php

namespace App\Src\Repositories;

use Carbon\Carbon;
use App\Src\Constantes;
use App\Models\Customer;
use App\Models\AfipVoucher;
use App\Models\SaleInvoices;
use Illuminate\Http\Request;
use App\Models\SaleInvoicesItem;
use Illuminate\Support\Facades\Log;
use App\Models\SaleInvoicesComments;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

use function PHPUnit\Framework\isNull;

class SaleInvoiceRepository
{

    public function find(Request $request): \Illuminate\Pagination\LengthAwarePaginator
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

    private function applyOptionalFilters($query, Request $request): void
    {
        if ($request->has('status_id')) {
            $query->where('status_id', $request->status_id);
        }

        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->has('from') && $request->has('to')) {
            $startDate = Carbon::createFromFormat('Y-m-d', $request->from);
            $endDate = Carbon::createFromFormat('Y-m-d', $request->to);
            $query->whereBetween('cbte_fch', [$startDate, $endDate]);
        }
    }

    /* public function find(Request $request)
    {
        $invoices = SaleInvoices::query();

        $invoices = $invoices->where('company_id', $request->company_id);

        if ($request->has('status_id')) {
            $invoices = $invoices->where('status_id', $request->status_id);
        }

        if ($request->has('customer_id')) {
            $invoices = $invoices->where('customer_id', $request->customer_id);
        }

        if ($request->has('from') && $request->has('to')) {
            $startDate = Carbon::createFromFormat('Y-m-d', $request->from);
            $endDate = Carbon::createFromFormat('Y-m-d', $request->to);
            $invoices = $invoices->whereBetween('cbte_fch', [$startDate, $endDate]);
        }

        $invoices = $invoices->orderBy('cbte_fch', 'desc')->orderBy('cbte_desde', 'desc');

        if ($request->has('print') && $request->get('print') === 'yes') {
            return $invoices->get();
        }

        return $invoices->paginate($request->per_page);
    } */

    /* public function store($data): SaleInvoices
    {
        $voucher = $data['FeCabReq']['CbteTipo'];

        if ($voucher < 100) {
            $voucher = '0' . $voucher;
        } else {
            $voucher = (string) $voucher;
        }

        $result = json_decode(json_encode($data['result']), true);

        $invoice = new SaleInvoices();

        $invoice->company_id = $data['company_id'];
        $invoice->customer_id = array_key_exists('value', $data['customer']) ? $data['customer']['value'] : $data['customer']['id'];
        $invoice->voucher_id = AfipVoucher::where('afip_code', $voucher)->get()->first()->id;
        $invoice->pto_vta = $data['FeCabReq']['PtoVta'];
        $invoice->cbte_desde = $data['FECAEDetRequest']['CbteDesde'];
        $invoice->cbte_hasta = $data['FECAEDetRequest']['CbteDesde'];
        $invoice->cbte_fch = Carbon::parse($data['FECAEDetRequest']['CbteFch'])->format('Y-m-d');
        $invoice->cae = $result['FECAESolicitarResult']['FeDetResp']['FECAEDetResponse'][0]['CAE'];
        $invoice->cae_fch_vto = Carbon::parse($result['FECAESolicitarResult']['FeDetResp']['FECAEDetResponse'][0]['CAEFchVto']);
        $invoice->user_id = $data['user_id'];
        $invoice->afip_data = collect($result)->toJson();
        $invoice->vto_payment = null;
        $invoice->commercial_reference = null;
        $invoice->payment_type_id = null;
        $invoice->sales_condition_id = $data['saleCondition']['id'];
        $invoice->status_id = ((int) $data['saleCondition']['days'] === 0) ? Constantes::CANCELADA : Constantes::ADEUDADA;
        $invoice->parent_id = array_key_exists('parent', $data) ? $data['parent'] : null;
        $invoice->save();

        if (array_key_exists('parent', $data) && !isNull($data['parent'])) {
            $si = SaleInvoices::find((int) $data['parent']);
            $si->parent_id = $invoice->id;
            $si->save();
        }

        collect($data['products'])->map(function ($product) use ($invoice) {

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
            $item->price_list_id = array_key_exists('priceList', $product) ? $product['priceList']['id'] : null;
            $item->voucher_id = null;
            $item->save();

            unset($item);
        });

        if ($data['comments']) {
            $sic = new SaleInvoicesComments;
            $sic->company_id = $data['company_id'];
            $sic->sale_invoice_id = $invoice->id;
            $sic->comment = $data['comments'];
            $sic->save();
        }

        $invoice->refresh();

        return $invoice;
    } */

    public function store($data): SaleInvoices
    {
        try {
            $voucher = $data['FeCabReq']['CbteTipo'];

            if ($voucher < 100) {
                $voucher = '0' . $voucher;
            } else {
                $voucher = (string) $voucher;
            }

            $result = json_decode(json_encode($data['result']), true);

            // Crear la nueva factura
            $invoice = new SaleInvoices();
            $invoice->company_id = $data['company_id'];
            $invoice->customer_id = array_key_exists('value', $data['customer']) ? $data['customer']['value'] : $data['customer']['id'];
            $invoice->voucher_id = AfipVoucher::where('afip_code', $voucher)->get()->first()->id;
            $invoice->pto_vta = $data['FeCabReq']['PtoVta'];
            $invoice->cbte_desde = $data['FECAEDetRequest']['CbteDesde'];
            $invoice->cbte_hasta = $data['FECAEDetRequest']['CbteDesde'];
            $invoice->cbte_fch = Carbon::parse($data['FECAEDetRequest']['CbteFch'])->format('Y-m-d');
            $invoice->cae = $result['FECAESolicitarResult']['FeDetResp']['FECAEDetResponse'][0]['CAE'];
            $invoice->cae_fch_vto = Carbon::parse($result['FECAESolicitarResult']['FeDetResp']['FECAEDetResponse'][0]['CAEFchVto']);
            $invoice->user_id = $data['user_id'];
            $invoice->afip_data = collect($result)->toJson();
            $invoice->vto_payment = null;
            $invoice->commercial_reference = null;
            $invoice->payment_type_id = null;
            $invoice->sales_condition_id = $data['saleCondition']['id'];
            $invoice->status_id = ((int) $data['saleCondition']['days'] === 0) ? Constantes::CANCELADA : Constantes::ADEUDADA;
            $invoice->parent_id = array_key_exists('parent', $data) ? $data['parent'] : null;
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

            return $invoice;
        } catch (\Exception $e) {
            // Registrar error y devolver mensaje informativo
            Log::error($e->getMessage());
            throw new \Exception('Error al crear la factura.');
        }
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
            $item->price_list_id = array_key_exists('priceList', $product) ? $product['priceList']['id'] : null;
            $item->voucher_id = null;
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
