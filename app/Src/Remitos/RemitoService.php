<?php

namespace App\Src\Remitos;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Remito;
use App\Models\RemitoItem;
use App\Models\SaleInvoices;
use App\Src\Services\Plans\PlanCatalog;
use App\Src\Services\Plans\PlanGate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reglas de negocio del remito.
 *
 * El documento que ampara el traslado de bienes sigue la RG 1415, Anexo V
 * ("DATOS QUE DEBEN CONTENER LOS REMITOS, LAS GUIAS, O DOCUMENTOS
 * EQUIVALENTES"). Tres ideas ordenan todo lo de abajo:
 *
 * 1. Un remito en BORRADOR no es un documento: se edita y no tiene número.
 *    Recién al emitirlo se le asigna el número del punto de venta y pasa a
 *    'emitido'. Desde ahí no se toca: se anula.
 *
 * 2. Al emitir se congela un SNAPSHOT con todo lo que se imprime. Es lo que
 *    permite reimprimir en cualquier momento y que salga idéntico: si después
 *    cambia el domicilio de la empresa o el nombre de un producto, el papel que
 *    ya se entregó al cliente no puede mutar.
 *
 * 3. El vínculo con las facturas es de VARIOS A VARIOS y vive en la tabla
 *    `remito_sale_invoice`. La RG 1415, Anexo II exige que la factura consigne
 *    "el o los remitos" que respalda la operación, y un remito puede facturarse
 *    en varias facturas (entregas parciales). Por eso el pivote guarda `importe`
 *    y la lista de facturas NO se congela: crece a medida que se factura.
 */
class RemitoService
{
    public const ESTADO_BORRADOR = 'borrador';

    public const ESTADO_EMITIDO = 'emitido';

    public const ESTADO_ANULADO = 'anulado';

    public function __construct(private readonly PlanGate $planGate)
    {
    }

    /**
     * Clase de remito que le corresponde a la empresa.
     *
     * La clase se guarda por empresa (`companies.remito_clase`) porque la
     * decisión depende de su situación registral y la define el contador. Si no
     * está configurada se deriva: Responsable Inscripto emite clase "R"; el
     * resto, clase "X" (el Anexo V, inciso V, punto 9.b.5 contempla
     * expresamente los remitos clase "X" impresos por sistemas).
     */
    public function clasePara(Company $empresa): string
    {
        $configurada = strtoupper((string) $empresa->remito_clase);

        if (in_array($configurada, ['R', 'X'], true)) {
            return $configurada;
        }

        $condicion = strtoupper((string) optional($empresa->afipInscription)->name);

        return str_contains($condicion, 'RESPONSABLE INSCRIPTO') ? 'R' : 'X';
    }

    public function crear(Company $empresa, array $datos, ?int $usuarioId): Remito
    {
        return DB::transaction(function () use ($empresa, $datos, $usuarioId) {
            $remito = new Remito();
            $remito->company_id = $empresa->id;
            $remito->user_id = $usuarioId;
            $remito->clase = $this->clasePara($empresa);
            $remito->estado = self::ESTADO_BORRADOR;
            // El punto de venta sale de la empresa, no del front: si no está
            // configurado no se puede numerar y esto tiene que fallar temprano.
            $remito->pto_vta = $this->puntoDeVenta($empresa);

            $this->aplicarDatos($remito, $datos);
            $remito->save();

            $this->guardarItems($remito, $datos['items'] ?? []);

            return $remito->fresh('items');
        });
    }

    public function actualizar(Remito $remito, array $datos): Remito
    {
        if (! $remito->esEditable()) {
            throw ValidationException::withMessages([
                'remito' => 'El remito ya fue emitido: no se edita. Para corregirlo hay que anularlo y crear uno nuevo.',
            ]);
        }

        return DB::transaction(function () use ($remito, $datos) {
            $this->aplicarDatos($remito, $datos);
            $remito->save();

            $remito->items()->delete();
            $this->guardarItems($remito, $datos['items'] ?? []);

            return $remito->fresh('items');
        });
    }

    /**
     * Emite el remito: le asigna número, congela el snapshot y lo marca emitido.
     *
     * La numeración es la del Anexo V: OCHO dígitos que arrancan en 00000001 y
     * son consecutivos y progresivos POR PUNTO DE VENTA. Se toma el máximo con
     * lock adentro de la transacción para que dos emisiones simultáneas no
     * saquen el mismo número (la tabla tiene además un único por empresa +
     * punto de venta + número, que es la red de seguridad).
     */
    public function emitir(Remito $remito): Remito
    {
        if ($remito->estado === self::ESTADO_ANULADO) {
            throw ValidationException::withMessages([
                'remito' => 'El remito está anulado: no se puede emitir.',
            ]);
        }

        if ($remito->estado === self::ESTADO_EMITIDO) {
            throw ValidationException::withMessages([
                'remito' => 'El remito ya estaba emitido.',
            ]);
        }

        if ($remito->items()->count() === 0) {
            throw ValidationException::withMessages([
                'items' => 'Un remito sin bienes no se puede emitir.',
            ]);
        }

        return DB::transaction(function () use ($remito) {
            $empresa = $remito->company;

            // El tope de remitos se controla ANTES de numerar y guardar: si no hay
            // lugar, la emisión se rechaza con el contrato del tope y el remito queda
            // como estaba (en borrador). El remito que YA existía y después se factura
            // no vuelve a consumir: acá sólo se cuenta lo que se emite.
            //
            // El consumo va en la misma transacción que el remito, así que si algo
            // falla después no queda un remito sin contar ni un lugar consumido sin
            // remito. La clave es el id del remito: reintentar la emisión no suma dos.
            $this->planGate->consume(
                (int) $remito->company_id,
                PlanCatalog::RESOURCE_REMITOS,
                'remito:' . $remito->id,
                (int) $remito->id
            );

            $ptoVenta = $this->puntoDeVenta($empresa);
            $remito->pto_vta = $ptoVenta;

            $ultimo = Remito::query()
                ->where('company_id', $remito->company_id)
                ->where('pto_vta', $ptoVenta)
                ->whereNotNull('number')
                ->lockForUpdate()
                ->max(DB::raw('CAST(`number` AS UNSIGNED)'));

            $remito->number = str_pad((string) (((int) $ultimo) + 1), 8, '0', STR_PAD_LEFT);
            $remito->clase = $remito->clase ?: $this->clasePara($empresa);
            $remito->estado = self::ESTADO_EMITIDO;
            $remito->emitido_at = now();
            $remito->snapshot = $this->construirSnapshot($remito, $empresa);
            $remito->total = $this->totalDe($remito);
            $remito->save();

            return $remito->fresh('items');
        });
    }

    /**
     * Anula el remito. No se borra: el Anexo V, inciso V, punto 11.b exige
     * conservar el documento inutilizado, archivado y con la leyenda "ANULADO".
     */
    public function anular(Remito $remito, string $motivo): Remito
    {
        if ($remito->estado === self::ESTADO_ANULADO) {
            throw ValidationException::withMessages([
                'remito' => 'El remito ya estaba anulado.',
            ]);
        }

        if ($remito->saleInvoices()->exists()) {
            throw ValidationException::withMessages([
                'remito' => 'El remito tiene facturas asociadas: primero hay que desvincularlas.',
            ]);
        }

        $remito->estado = self::ESTADO_ANULADO;
        $remito->anulado_at = now();
        $remito->anulado_motivo = $motivo;
        $remito->save();

        return $remito;
    }

    /**
     * Lo que el generador de PDF necesita para dibujar el remito.
     *
     * Si el remito está emitido se devuelve el SNAPSHOT congelado (reimpresión
     * idéntica). Si todavía es un borrador se arma en el momento, para poder
     * previsualizarlo antes de emitir.
     *
     * Lo único que NO sale del snapshot es la lista de facturas: un remito se
     * factura en partes, así que esa lista tiene que reflejar el estado actual.
     */
    public function datosParaPdf(Remito $remito): array
    {
        $base = $remito->snapshot ?: $this->construirSnapshot($remito, $remito->company);

        $base['id'] = $remito->id;
        $base['estaAnulado'] = $remito->estado === self::ESTADO_ANULADO;
        $base['estado'] = $remito->estado;
        $base['anuladoMotivo'] = $remito->anulado_motivo;
        $base['facturas'] = $remito->saleInvoices()
            ->orderBy('sale_invoices.id')
            ->get()
            ->map(fn (SaleInvoices $factura) => [
                'id' => $factura->id,
                'numero' => $this->numeroDeComprobante($factura),
                'fecha' => optional($factura->cbte_fch)->format('d/m/Y'),
                'importe' => (float) $factura->pivot->importe,
            ])
            ->values()
            ->all();

        return $base;
    }

    /**
     * Remitos emitidos de un cliente a los que todavía les queda algo por
     * facturar. Es lo que alimenta el selector al emitir una factura.
     */
    public function pendientesDeFacturar(Company $empresa, ?int $customerId = null)
    {
        return Remito::query()
            ->with(['items.product', 'customer', 'saleInvoices'])
            ->where('company_id', $empresa->id)
            ->where('estado', self::ESTADO_EMITIDO)
            ->when($customerId, fn ($query, $id) => $query->where('customer_id', $id))
            ->orderByDesc('id')
            ->get()
            ->filter(fn (Remito $remito) => $remito->importePendiente() > 0.009)
            ->values();
    }

    /**
     * Vincula un remito a una factura por un importe.
     *
     * Se usa desde la emisión de la factura. Vincula de nuevo si ya existía:
     * el pivote tiene un único por par, así que se actualiza el importe.
     */
    public function vincularFactura(Remito $remito, SaleInvoices $factura, float $importe): void
    {
        $remito->saleInvoices()->syncWithoutDetaching([
            $factura->id => ['importe' => round($importe, 2)],
        ]);
    }

    /**
     * Reemplaza los vínculos de una factura por los que llegan del front.
     *
     * Se llama al emitir una factura con la lista de remitos que cubre. Los
     * vínculos que ya no vienen se sueltan.
     *
     * @param array<int, array{remitoId:int, importe:float}> $vinculos
     */
    public function sincronizarFacturasDeLaFactura(SaleInvoices $factura, array $vinculos): void
    {
        DB::transaction(function () use ($factura, $vinculos) {
            $armado = [];
            foreach ($vinculos as $vinculo) {
                $armado[(int) $vinculo['remitoId']] = ['importe' => round((float) $vinculo['importe'], 2)];
            }

            $factura->remitos()->sync($armado);
        });
    }

    /** Número completo del remito tal como se imprime: PPPPP-NNNNNNNN. */
    public function numeroCompleto(Remito $remito): ?string
    {
        if (! $remito->number) {
            return null;
        }

        return str_pad((string) $remito->pto_vta, 5, '0', STR_PAD_LEFT)
            .'-'
            .str_pad((string) $remito->number, 8, '0', STR_PAD_LEFT);
    }

    /**
     * Arma el documento imponible. Se congela al emitir.
     */
    public function construirSnapshot(Remito $remito, ?Company $empresa): array
    {
        $empresa = $empresa ?: $remito->company;
        $cliente = $remito->customer;

        return [
            'clase' => $remito->clase ?: $this->clasePara($empresa),
            'puntoVenta' => (int) $remito->pto_vta,
            'numero' => $remito->number ? str_pad((string) $remito->number, 8, '0', STR_PAD_LEFT) : null,
            'numeroCompleto' => $this->numeroCompleto($remito),
            'fechaEmision' => $remito->emitido_at
                ? $remito->emitido_at->format('Y-m-d')
                : now()->format('Y-m-d'),
            'fechaEntrega' => $remito->delivery_date,
            'emisor' => $this->emisor($empresa),
            'destinatario' => $this->destinatario($cliente, $remito),
            'condicionVenta' => optional($remito->saleCondition)->name ?? $remito->commercial_reference,
            'referenciaComercial' => $remito->commercial_reference,
            'transportista' => $remito->transportista_nombre
                ? [
                    'nombre' => $remito->transportista_nombre,
                    'cuit' => $remito->transportista_cuit,
                    'domicilio' => $remito->transportista_domicilio,
                ]
                : null,
            'items' => $remito->items->map(fn (RemitoItem $item) => [
                'cantidad' => (float) $item->quantity,
                'descripcion' => optional($item->product)->name ?? '',
                'precioUnitario' => (float) $item->neto,
                'total' => (float) $item->total,
            ])->values()->all(),
            'total' => $this->totalDe($remito),
        ];
    }

    // ------------------------------------------------------------------ privados

    private function puntoDeVenta(Company $empresa): int
    {
        $ptoVenta = (int) $empresa->pto_vta_remito;

        if ($ptoVenta <= 0) {
            throw ValidationException::withMessages([
                'pto_vta_remito' => 'La empresa no tiene configurado el punto de venta de remitos.',
            ]);
        }

        return $ptoVenta;
    }

    private function aplicarDatos(Remito $remito, array $datos): void
    {
        $remito->customer_id = $datos['customerId'];
        $remito->delivery_date = $datos['fechaEntrega'] ?? null;
        $remito->direccion_entrega = $datos['direccionEntrega'] ?? null;
        $remito->commercial_reference = $datos['referenciaComercial'] ?? null;
        $remito->payment_type_id = $datos['paymentTypeId'] ?? null;
        $remito->sales_condition_id = $datos['saleConditionId'] ?? null;
        $remito->status_id = $datos['statusId'] ?? null;
        $remito->observaciones = $datos['observaciones'] ?? null;

        $transportista = $datos['transportista'] ?? null;
        $remito->transportista_nombre = $transportista['nombre'] ?? null;
        $remito->transportista_cuit = $transportista['cuit'] ?? null;
        $remito->transportista_domicilio = $transportista['domicilio'] ?? null;

        $remito->total = $this->totalDeItems($datos['items'] ?? []);
    }

    private function guardarItems(Remito $remito, array $items): void
    {
        foreach ($items as $item) {
            $cantidad = (float) ($item['cantidad'] ?? 0);
            $precio = (float) ($item['precioUnitario'] ?? 0);
            $neto = round($cantidad * $precio, 2);
            $producto = Product::find($item['productId']);

            // El IVA del remito es informativo: el remito no discrimina IVA.
            $alicuota = (float) (optional(optional($producto)->iva)->percentage ?? 0);

            $renglon = new RemitoItem();
            $renglon->remito_id = $remito->id;
            $renglon->product_id = $item['productId'];
            $renglon->quantity = $cantidad;
            $renglon->neto = $neto;
            $renglon->iva = round($neto * $alicuota / 100, 2);
            $renglon->total = round($neto + $renglon->iva, 2);
            $renglon->save();
        }

        $remito->total = $remito->items()->sum('total');
        $remito->save();
    }

    private function totalDeItems(array $items): float
    {
        return round(array_reduce($items, function (float $acc, array $item) {
            return $acc + ((float) ($item['cantidad'] ?? 0) * (float) ($item['precioUnitario'] ?? 0));
        }, 0.0), 2);
    }

    private function totalDe(Remito $remito): float
    {
        $remito->loadMissing('items');

        return round((float) $remito->items->sum('total'), 2);
    }

    /**
     * Datos del emisor. Salen de la empresa y quedan congelados en el snapshot:
     * si la empresa cambia de domicilio, los remitos ya emitidos no cambian.
     */
    private function emisor(?Company $empresa): array
    {
        if (! $empresa instanceof Company) {
            return [];
        }

        $direccion = $empresa->address;
        $localidad = $direccion && $direccion->state_id
            ? (string) DB::table('states')->where('id', $direccion->state_id)->value('name')
            : '';

        $calle = trim(implode(' ', array_filter([
            $direccion->street ?? null,
            $direccion->number ?? null,
        ])));

        return [
            'razonSocial' => trim(($empresa->name ?? '').' '.($empresa->last_name ?? '')),
            'domicilio' => $calle,
            'localidad' => trim(implode(' ', array_filter([
                $direccion->city ?? '',
                $localidad,
                $direccion->cp ? '('.$direccion->cp.')' : '',
            ]))),
            'cuit' => $empresa->afip_number,
            'ingresosBrutos' => $empresa->iibb_conv,
            'inicioActividades' => $empresa->activity_init,
            'condicionIva' => optional($empresa->afipInscription)->name,
            'email' => $empresa->email,
            'telefono' => $empresa->phone1,
        ];
    }

    /**
     * Datos del destinatario. La dirección de ENTREGA puede diferir del
     * domicilio del cliente, por eso se usa la del remito si está cargada.
     */
    private function destinatario(?Customer $cliente, Remito $remito): array
    {
        $direccion = $cliente ? $cliente->address : null;

        $domicilioCliente = trim(implode(', ', array_filter([
            trim(implode(' ', array_filter([$direccion->street ?? null, $direccion->number ?? null]))),
            $direccion->city ?? null,
            $direccion->cp ? '('.$direccion->cp.')' : null,
        ])));

        return [
            'razonSocial' => $cliente
                ? trim(($cliente->name ?? '').' '.($cliente->last_name ?? ''))
                : '',
            'domicilio' => $remito->direccion_entrega ?: $domicilioCliente,
            'cuit' => $cliente->afip_number ?? null,
            'condicionIva' => optional(optional($cliente)->afipInscription)->name,
        ];
    }

    private function numeroDeComprobante(SaleInvoices $factura): string
    {
        return str_pad((string) $factura->pto_vta, 5, '0', STR_PAD_LEFT)
            .'-'
            .str_pad((string) $factura->cbte_desde, 8, '0', STR_PAD_LEFT);
    }
}
