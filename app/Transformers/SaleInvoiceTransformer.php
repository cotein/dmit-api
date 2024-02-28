<?php

namespace App\Transformers;

use App\Models\AfipState;
use App\Models\SaleInvoices;
use App\Src\Constantes;
use App\Src\Helpers\ZeroLeft;
use Carbon\Carbon;
use League\Fractal\TransformerAbstract;

class SaleInvoiceTransformer extends TransformerAbstract
{
    /**
     * List of resources to automatically include
     *
     * @var array
     */
    protected array $defaultIncludes = [
        //
    ];

    /**
     * List of resources possible to include
     *
     * @var array
     */
    protected array $availableIncludes = [
        //
    ];

    /**
     * Obtener el número de cada iteración.
     *
     * @return int
     */
    protected function getCurrentIteration()
    {
        static $iteration = 0;
        $iteration++;
        return $iteration;
    }

    protected function items(SaleInvoices $si): array
    {
        return $si->items->map(function ($item) {
            return [
                'id' => $item->product->id,
                'key' => $item->id,
                'name' => $item->product->name,
                'quantity' => $item->quantity,
                'neto_import' => $item->neto_import,
                'iva_import' => $item->iva_import,
                'iva_afip_code' => $item->iva->code,
                'iva_id' => $item->iva->id,
                'unit_price' => $item->unit_price,
                'total' => $item->total
            ];
        })->toArray();
    }

    protected function totalInvoice(SaleInvoices $si)
    {
        return $si->items->sum('total');
    }

    /**
     * Returns the address of the customer
     *
     * @param SaleInvoices $si The sale invoice model
     * @return array|null The customer's address or null if the customer does not have an address
     */
    protected function address(SaleInvoices $si): ?array
    {
        if ($si->customer->address()->exists()) {
            return [
                'city' => $si->customer->address->city,
                'street' => $si->customer->address->street,
                'cp' => $si->customer->address->cp,
                'state' => AfipState::where('afip_code', $si->customer->address->state_id)->get()->first()->name
            ];
        }

        return null;
    }

    protected function concepto(array $afip_data): int
    {
        return $afip_data['FECAESolicitarResult']['FeDetResp']['FECAEDetResponse'][0]['Concepto'];
    }

    protected function comprAsociado(array $afip_data): array
    {
        return [
            'Tipo' => $afip_data['FECAESolicitarResult']['FeCabResp']['CbteTipo'],
            'PtoVta' => $afip_data['FECAESolicitarResult']['FeCabResp']['PtoVta'],
            'Nro' => $afip_data['FECAESolicitarResult']['FeDetResp']['FECAEDetResponse'][0]['CbteDesde'],
            'Cuit' => $afip_data['FECAESolicitarResult']['FeCabResp']['Cuit'], //emisor
            'CbteFch' => $afip_data['FECAESolicitarResult']['FeDetResp']['FECAEDetResponse'][0]['CbteFch'],
        ];
    }

    protected function typeNotaCredito(array $afip_data)
    {
        $invoices = [
            1 => 3,
            2 => 3,
            6 => 8,
            7 => 8,
            11 => 13,
            12 => 13,
            201 => 203,
            202 => 203,
            206 => 208,
            207 => 208,
            211 => 213,
            212 => 213,
        ];

        $cbteTipo = (int) $afip_data['FECAESolicitarResult']['FeCabResp']['CbteTipo'];

        if (array_key_exists($cbteTipo, $invoices)) {
            return $invoices[$cbteTipo];
        }

        return null;
    }

    protected function typeNotaDebito(array $afip_data)
    {
        $invoices = [
            1 => 2,
            6 => 7,
            11 => 12,
            201 => 202,
            206 => 207,
            211 => 212,
        ];

        $cbteTipo = (int) $afip_data['FECAESolicitarResult']['FeCabResp']['CbteTipo'];

        if (array_key_exists($cbteTipo, $invoices)) {
            return $invoices[$cbteTipo];
        }

        return null;
    }

    protected function isNotaCredito($afip_data): bool
    {
        $invoices = [
            3 => true,
            8 => true,
            13 => true,
            203 => true,
            208 => true,
            213 => true,
        ];

        $invoiceAfipCode = (int) $afip_data['FECAESolicitarResult']['FeCabResp']['CbteTipo'];

        if (array_key_exists($invoiceAfipCode, $invoices)) {
            return $invoices[$invoiceAfipCode];
        }

        return false;
    }

    protected function isNotaDebito($afip_data): bool
    {
        $invoices = [
            2 => true,
            7 => true,
            12 => true,
            202 => true,
            207 => true,
            212 => true,
        ];

        $invoiceAfipCode = (int) $afip_data['FECAESolicitarResult']['FeCabResp']['CbteTipo'];

        if (array_key_exists($invoiceAfipCode, $invoices)) {
            return $invoices[$invoiceAfipCode];
        }

        return false;
    }

    private function parents(SaleInvoices $si): array
    {
        if ($si->parents) {
            return $si->parents->map(function ($invoice) {
                return [
                    'invoice_id' => $invoice->id,
                    'invoice' => $invoice->voucher->name . ' ' .  ZeroLeft::print($invoice->pto_vta, 4) . '-' . ZeroLeft::print($invoice->cbte_desde, 8) . ' $' . number_format($invoice->items->sum('total'), 2, ',', '.'),
                ];
            })->toArray();
        }

        return [];
    }

    /**
     * A Fractal transformer.
     *
     * @return array
     */
    public function transform(SaleInvoices $si)
    {
        $afip_data = json_decode($si->afip_data, TRUE);

        return [
            'key' => $this->getCurrentIteration(),
            'id' => $si->id,
            'company' => [
                'id' => $si->company->id,
                'name' => $si->company->name,
                'last_name' => $si->company->last_name,
                'fantasy_name' => $si->company->fantasy_name,
                'cuit' => $si->company->afip_number,
                'afipInscription' => $si->company->afipInscription->name,
                'afipDocument' => $si->company->afipDocument->name,
                'activity_init' => $si->company->activity_init,
                'iibb' => $si->company->iibb_conv,
                'address' => [
                    'city' => $si->company->address->city,
                    'street' => $si->company->address->street,
                    'cp' => $si->company->address->cp,
                    'state' => AfipState::where('afip_code', $si->company->address->state_id)->get()->first()->name
                ]
            ],
            'customer' => [
                'id' => $si->customer->id,
                'name' => $si->customer->name,
                'last_name' => $si->customer->last_name,
                'fantasy_name' => $si->customer->fantasy_name,
                'cuit' => $si->customer->afip_number,
                'afipInscription' => $si->customer->afipInscription->name,
                'afipInscription_id' => $si->customer->afipInscription->id,
                'afipDocument' => $si->customer->afipDocument->name,
                'afipDocTipo' => $si->customer->afipDocument->afip_code,
                'address' => $this->address($si)
            ],
            'voucher' => [
                'name' => $si->voucher->name,
                'pto_vta' => ZeroLeft::print($si->pto_vta, 4),
                'cbte_desde' => ZeroLeft::print($si->cbte_desde, 8),
                'cbte_fch' => Carbon::parse($si->cbte_fch)->format('d-m-Y'),
                'cae' => $si->cae,
                'cae_fch_vto' => Carbon::parse($si->cae_fch_vto)->format('d-m-Y'),
                'sale_conditions' => $si->saleCondition->name,
                'sale_conditions_id' => $si->saleCondition->id,
                'voucher_type' => $si->voucher->id,
                'status' => $si->status_id,
                'concepto' => $this->concepto($afip_data),
                'cbteAsoc' => $this->comprAsociado($afip_data),
                'total' => $this->totalInvoice($si),
                'typeNotaCredito' => $this->typeNotaCredito($afip_data),
                'typeNotaDebito' => $this->typeNotaDebito($afip_data),
                'isNotaCredito' => $this->isNotaCredito($afip_data),
                'isNotaDebito' => $this->isNotaDebito($afip_data),
                'parents' => $this->parents($si)
            ],
            'items' => $this->items($si),
            'comment' => ($si->comments()->exists()) ? $si->comments->comment : '',

        ];
    }
}
