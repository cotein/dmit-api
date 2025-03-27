<?php

namespace App\Transformers;

use App\Models\AfipState;
use App\Models\SaleInvoices;
use App\Src\Constantes;
use App\Src\Helpers\ZeroLeft;
use App\Src\Traits\AddressTrait;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use League\Fractal\TransformerAbstract;


class SaleInvoiceTransformer extends TransformerAbstract
{
    use AddressTrait;
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
                'discount_percentage' => $item->discount_percentage,
                'discount_import' => $item->discount_import,
                'price_list_id' => $item->price_list_id,
                'percep_iibb_alicuota' => (float) $item->percep_iibb_alicuota,
                'percep_iibb_import' => (float) $item->percep_iibb_import,
                'percep_iva_alicuota' => (float) $item->percep_iva_alicuota,
                'percep_iva_import' => (float) $item->percep_iva_import,
                //'unit_price' => number_format($item->neto_import / $item->quantity, 2, ',', '.'),
                'total' => $item->total
            ];
        })->toArray();
    }

    protected function totalInvoice(SaleInvoices $si)
    {
        return $si->items->sum('total') + $si->items->sum('percep_iibb_import') + $si->items->sum('percep_iva_import');
    }

    /**
     * Returns the address of the customer
     *
     * @param SaleInvoices $si The sale invoice model
     * @return array|null The customer's address or null if the customer does not have an address
     */
    /* protected function address(SaleInvoices $si): ?array
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
    } */

    /* protected function concepto(array $afip_data): int
    {
        return $afip_data['FECAESolicitarResult']['FeDetResp']['FECAEDetResponse'][0]['Concepto'];
    } */

    protected function concepto(mixed $afip_data): int
    {
        // Si es string, decodificar primero
        if (is_string($afip_data)) {
            $afip_data = trim($afip_data, '"');
            $decoded = json_decode($afip_data, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                \Log::error('Error decodificando afip_data en concepto()', [
                    'input' => $afip_data,
                    'error' => json_last_error_msg()
                ]);
                return 1; // Valor por defecto seguro
            }

            $afip_data = $decoded;
        }

        // Verificar estructura completa del array
        try {
            return $afip_data['FECAESolicitarResult']['FeDetResp']['FECAEDetResponse'][0]['Concepto'] ?? 1;
        } catch (\Throwable $e) {
            \Log::warning('Estructura AFIP inválida en concepto()', [
                'exception' => $e->getMessage(),
                'data' => $afip_data
            ]);
            return 1; // Valor por defecto según RG AFIP
        }
    }
    protected function comprAsociado(mixed $afip_data): array
    {
        // Si es string, decodificar primero
        if (is_string($afip_data)) {
            // Eliminar comillas exteriores si existen
            $afip_data = trim($afip_data, '"');

            // Decodificar el JSON interno
            $decoded = json_decode($afip_data, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                \Log::error('Error decodificando afip_data', [
                    'input' => $afip_data,
                    'error' => json_last_error_msg()
                ]);
                return [];
            }

            $afip_data = $decoded;
        }

        // Verificar que ahora sea un array
        if (!is_array($afip_data)) {
            return [];
        }

        // Resto de la lógica original...
        try {
            return [
                'Tipo' => $afip_data['FECAESolicitarResult']['FeCabResp']['CbteTipo'],
                'PtoVta' => $afip_data['FECAESolicitarResult']['FeCabResp']['PtoVta'],
                'Nro' => $afip_data['FECAESolicitarResult']['FeDetResp']['FECAEDetResponse']['CbteDesde'],
                'Cuit' => $afip_data['FECAESolicitarResult']['FeCabResp']['Cuit'],
                'CbteFch' => $afip_data['FECAESolicitarResult']['FeDetResp']['FECAEDetResponse']['CbteFch']
            ];
        } catch (\Exception $e) {
            \Log::error('Error procesando afip_data', [
                'exception' => $e->getMessage(),
                'data' => $afip_data
            ]);
            return [];
        }
    }
    /* protected function comprAsociado(array $afip_data): array
    {
        if (!is_array($afip_data) || empty($afip_data)) {

            Log::warning('Datos AFIP inválidos', [
                'tipo_recibido' => gettype($afip_data),
                'esta_vacio' => is_array($afip_data) && empty($afip_data),
                'contenido' => $afip_data
            ]);

            return [];
        }

        return [
            'Tipo' => $afip_data['FECAESolicitarResult']['FeCabResp']['CbteTipo'],
            'PtoVta' => $afip_data['FECAESolicitarResult']['FeCabResp']['PtoVta'],
            'Nro' => $afip_data['FECAESolicitarResult']['FeDetResp']['FECAEDetResponse'][0]['CbteDesde'],
            'Cuit' => $afip_data['FECAESolicitarResult']['FeCabResp']['Cuit'], //emisor
            'CbteFch' => $afip_data['FECAESolicitarResult']['FeDetResp']['FECAEDetResponse'][0]['CbteFch'],
        ];
    } */

    /* protected function typeNotaCredito(array $afip_data)
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
    } */

    private function getTipoComprobanteMapeado(mixed $afip_data, array $mapeo)
    {
        try {
            $data = is_string($afip_data)
                ? json_decode(trim($afip_data, '"'), true)
                : $afip_data;

            if (!is_array($data)) {
                \Log::warning('Datos AFIP no son un array', ['data' => $afip_data]);
                return null;
            }

            $cbteTipo = (int) ($data['FECAESolicitarResult']['FeCabResp']['CbteTipo'] ?? 0);
            return $mapeo[$cbteTipo] ?? null;

        } catch (\Throwable $e) {
            \Log::error('Error obteniendo tipo comprobante mapeado', [
                'error' => $e->getMessage(),
                'data' => $afip_data
            ]);
            return null;
        }
    }

    protected function typeNotaCredito(mixed $afip_data)
    {
        return $this->getTipoComprobanteMapeado($afip_data, [
            1 => 3, 2 => 3, 6 => 8, 7 => 8, 11 => 13, 12 => 13,
            201 => 203, 202 => 203, 206 => 208, 207 => 208, 211 => 213, 212 => 213
        ]);
    }

    protected function typeNotaDebito(mixed $afip_data)
    {
        return $this->getTipoComprobanteMapeado($afip_data, [
            1 => 2, 6 => 7, 11 => 12, 201 => 202, 206 => 207, 211 => 212
        ]);
    }
    /* protected function isNotaCredito(array $afip_data): bool
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

    protected function isNotaDebito(array $afip_data): bool
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
    } */

    private function checkTipoComprobante(mixed $afip_data, array $tipos): bool
    {
        try {
            $data = is_string($afip_data)
                ? json_decode(trim($afip_data, '"'), true)
                : $afip_data;

            if (!is_array($data)) {
                return false;
            }

            $codigo = (int) ($data['FECAESolicitarResult']['FeCabResp']['CbteTipo'] ?? 0);
            return $tipos[$codigo] ?? false;

        } catch (\Throwable $e) {
            \Log::error('Error verificando tipo comprobante', [
                'error' => $e->getMessage(),
                'data' => $afip_data
            ]);
            return false;
        }
    }

    protected function isNotaCredito(mixed $afip_data): bool
    {
        return $this->checkTipoComprobante($afip_data, [
            3 => true, 8 => true, 13 => true,
            203 => true, 208 => true, 213 => true
        ]);
    }

    protected function isNotaDebito(mixed $afip_data): bool
    {
        return $this->checkTipoComprobante($afip_data, [
            2 => true, 7 => true, 12 => true,
            202 => true, 207 => true, 212 => true
        ]);
    }
    private function formatInvoiceData(SaleInvoices $invoice): array
    {
        return [
            'company_id' => $invoice->company->id,
            'invoice_id' => $invoice->id,
            'invoice' => $invoice->voucher->name . ' ' .  ZeroLeft::print($invoice->pto_vta, 4) . '-' . ZeroLeft::print($invoice->cbte_desde, 8) . ' $' . number_format($invoice->items->sum('total'), 2, ',', '.'),
            'items' => $this->items($invoice)
        ];
    }

    private function parents(SaleInvoices $si): array
    {
        if ($si->parents) {
            return $si->parents->map(function ($invoice) {
                return $this->formatInvoiceData($invoice);
            })->toArray();
        }

        return [];
    }

    private function children(SaleInvoices $si): array
    {
        if ($si->children) {
            return $si->children->map(function ($invoice) {
                return $this->formatInvoiceData($invoice);
            })->toArray();
        }

        return [];
    }

    private function isExpirated($si): bool
    {
        if ($si->sale_condition_id != 1) { //1 = contado
            return Carbon::parse($si->fch_vto_pago)->isPast();
        }
        return false;
    }
    /**
     * A Fractal transformer.
     *
     * @return array
     */
    public function transform(SaleInvoices $si)
    {
        // Verificar si existe la relación customer
        if (!$si->customer) {
            Log::info('########');
            Log::warning('Customer no encontrado - Registro omitido', [
                'invoice_id' => $si->id,
                'customer_id' => $si->customer_id,
                'company_id' => $si->company_id
            ]);
            Log::info('########');
            // Retornar null para omitir este registro
            return null;
        }
        $afip_data = json_decode($si->afip_data, true);

        // Verificar si hubo errores
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Error decodificando JSON: ' . json_last_error_msg());
        }

        $url_logo = null;

        if ($si->company->hasMedia('logos')) {
            $url_logo = $si->company->getMedia('logos')->first()->getFullUrl();
        }

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
                'afipInscription_id' => $si->company->afipInscription->id,
                'afipDocument' => $si->company->afipDocument->name,
                'activity_init' => $si->company->activity_init,
                'iibb' => $si->company->iibb_conv,
                /* 'address' => [
                    'city' => $si->company->address->city,
                    'street' => $si->company->address->street,
                    'cp' => $si->company->address->cp,
                    'state' => AfipState::where('afip_code', $si->company->address->state_id)->get()->first()->name
                ], */
                'address' => $this->address($si->company),
                //'urlLogo' => $si->company->getMedia('logos')->first()->getFullUrl(),
                'urlLogo' => $url_logo,
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
                'address' => $this->address($si->customer)
            ],

            'voucher' => [
                'cae_fch_vto' => Carbon::parse($si->cae_fch_vto)->format('d-m-Y'),
                'cae' => $si->cae,
                'cbte_desde' => ZeroLeft::print($si->cbte_desde, 8),
                'cbte_fch' => Carbon::parse($si->cbte_fch)->format('d-m-Y'),
                'cbteAsoc' => $this->comprAsociado($afip_data),
                'children' => $this->children($si), //cuando una factura tiene nota de credito
                'concepto' => $this->concepto($afip_data),
                'fch_serv_desde' => $si->fch_serv_desde,
                'fch_serv_hasta' => $si->fch_serv_hasta,
                'fch_vto_pago' => Carbon::parse($si->fch_vto_pago)->format('d-m-Y'),
                'isNotaCredito' => $this->isNotaCredito($afip_data),
                'isNotaDebito' => $this->isNotaDebito($afip_data),
                'name' => $si->voucher->name,
                'parents' => $this->parents($si), //cuando una nota de credito pertenece a una factura
                'payment_type_id' => ($si->paymentType()->exists()) ? $si->paymentType->id : null,
                'payment_type' => ($si->paymentType()->exists()) ? strtoupper($si->paymentType->name) : null,
                'periodoAsoc' => [
                    'FchDesde' => Carbon::parse($si->fch_serv_desde)->format('Ymd'),
                    'FchHasta' => Carbon::parse($si->fch_serv_hasta)->format('Ymd'),
                ],
                'pto_vta' =>  ZeroLeft::print($si->pto_vta, 4),
                'sale_conditions_id' => $si->saleCondition->id,
                'sale_conditions' => strtoupper($si->saleCondition->name),
                'status' => $si->status_id,
                'total' => $this->totalInvoice($si),
                'typeNotaCredito' => $this->typeNotaCredito($afip_data),
                'typeNotaDebito' => $this->typeNotaDebito($afip_data),
                'voucher_id' => $si->id,
                'voucher_type' => $si->voucher->id,
                'voucher_type_afip_code' => $si->voucher->afip_code,
                'nota_credito_o_debito_text' => 'Sobre: ' .  $si->voucher->name . ' ' . ZeroLeft::print($si->pto_vta, 4) . ' - ' . ZeroLeft::print($si->cbte_desde, 8),
                'isExpirated' => $this->isExpirated($si),
            ],

            'items' => $this->items($si),
            'comment' => ($si->comments()->exists()) ? $si->comments->comment : '',
        ];
    }
}
