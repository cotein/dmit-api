<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Remito;
use App\Models\SaleInvoices;
use App\Src\Remitos\RemitoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Endpoints del remito.
 *
 * Un remito es el documento que ampara el traslado y la entrega de los bienes.
 * Sigue la RG 1415, Anexo V. NO es un comprobante fiscal: por eso no pide CAE
 * ni QR, y por eso lleva impresa la leyenda "DOCUMENTO NO VALIDO COMO FACTURA".
 *
 * Ciclo de vida: borrador -> emitido -> (anulado). Un remito emitido no se
 * edita ni se borra: se anula, que es lo que exige el Anexo V para el documento
 * que se inutiliza.
 *
 * El vínculo con las facturas es de varios a varios y se maneja desde el flujo
 * de facturación (ver el endpoint de facturas); acá sólo se expone para leerlo.
 */
class RemitoController extends Controller
{
    public function __construct(private readonly RemitoService $service)
    {
    }

    /**
     * GET remitos
     *
     * Listado con filtros por estado, cliente y texto libre. Devuelve además,
     * por remito, cuánto se facturó y cuánto falta facturar.
     */
    public function index(Request $request): JsonResponse
    {
        $empresa = $this->empresa($request);

        $remitos = Remito::query()
            ->with(['items', 'customer', 'saleInvoices'])
            ->when($empresa, fn ($query) => $query->where('remitos.company_id', $empresa->id))
            ->when($request->query('estado'), fn ($query, $estado) => $query->where('estado', $estado))
            ->when($request->query('customerId'), fn ($query, $id) => $query->where('customer_id', $id))
            ->when($request->query('search'), function ($query, $texto) {
                $query->where(function ($sub) use ($texto) {
                    $sub->where('number', 'like', '%'.$texto.'%')
                        ->orWhere('commercial_reference', 'like', '%'.$texto.'%')
                        ->orWhereHas('customer', function ($cliente) use ($texto) {
                            $cliente->where('name', 'like', '%'.$texto.'%')
                                ->orWhere('last_name', 'like', '%'.$texto.'%')
                                ->orWhere('afip_number', 'like', '%'.$texto.'%');
                        });
                });
            })
            ->orderByDesc('id')
            ->paginate((int) min(max((int) $request->query('per_page', 20), 1), 100));

        return response()->json([
            'data' => collect($remitos->items())->map(fn (Remito $remito) => $this->presentar($remito)),
            'meta' => [
                'currentPage' => $remitos->currentPage(),
                'lastPage' => $remitos->lastPage(),
                'perPage' => $remitos->perPage(),
                'total' => $remitos->total(),
            ],
        ]);
    }

    /**
     * GET remitos/pendientes
     *
     * Remitos emitidos a los que les queda algo por facturar. Es lo que alimenta
     * el selector al emitir una factura.
     *
     * OJO: esta ruta va ANTES de `remitos/{remito}`, si no Laravel intenta
     * resolver "pendientes" como un id de remito y devuelve 404.
     */
    public function pendientes(Request $request): JsonResponse
    {
        $empresa = $this->empresa($request, required: true);

        $remitos = $this->service->pendientesDeFacturar(
            $empresa,
            $request->query('customerId') ? (int) $request->query('customerId') : null
        );

        return response()->json([
            'data' => $remitos->map(fn (Remito $remito) => $this->presentar($remito)),
        ]);
    }

    /**
     * POST remitos
     */
    public function store(Request $request): JsonResponse
    {
        $empresa = $this->empresa($request, required: true);
        $datos = $this->validar($request);

        $remito = $this->service->crear($empresa, $datos, $request->user()?->id);

        return response()->json(['data' => $this->presentar($remito)], 201);
    }

    /**
     * GET remitos/{remito}
     */
    public function show(Request $request, Remito $remito): JsonResponse
    {
        $this->autorizar($request, $remito);

        return response()->json(['data' => $this->presentar($remito->load(['items', 'customer', 'saleInvoices']))]);
    }

    /**
     * PUT remitos/{remito}
     *
     * Sólo sobre un borrador.
     */
    public function update(Request $request, Remito $remito): JsonResponse
    {
        $this->autorizar($request, $remito);
        $datos = $this->validar($request);

        $remito = $this->service->actualizar($remito, $datos);

        return response()->json(['data' => $this->presentar($remito)]);
    }

    /**
     * DELETE remitos/{remito}
     *
     * Sólo un borrador se puede borrar. Un remito emitido se anula: el Anexo V
     * obliga a conservarlo archivado.
     */
    public function destroy(Request $request, Remito $remito): JsonResponse
    {
        $this->autorizar($request, $remito);

        if (! $remito->esEditable()) {
            throw ValidationException::withMessages([
                'remito' => 'Un remito emitido no se borra: se anula.',
            ]);
        }

        $remito->items()->delete();
        $remito->delete();

        return response()->json(['data' => ['id' => $remito->id]]);
    }

    /**
     * GET remitos/de-factura/{saleInvoice}
     *
     * Los remitos que respalda una factura. Es lo que la factura imprime en su
     * bloque "REMITOS ASOCIADOS" (RG 1415, Anexo II, Apartado A, Título I, e).
     *
     * NO sale del snapshot del remito: es una relación viva, porque un remito se
     * puede facturar en varias facturas y esta lista tiene que reflejar el
     * estado actual.
     */
    public function deFactura(Request $request, int $saleInvoice): JsonResponse
    {
        $empresa = $this->empresa($request, required: true);

        $factura = SaleInvoices::with('remitos')->find($saleInvoice);

        if (! $factura || (int) $factura->company_id !== (int) $empresa->id) {
            abort(404, 'La factura no existe.');
        }

        return response()->json([
            'data' => $factura->remitos->map(fn (Remito $remito) => [
                'id' => $remito->id,
                'numero' => $this->service->numeroCompleto($remito),
                'fecha' => optional($remito->emitido_at)->format('d/m/Y'),
                'importe' => (float) $remito->pivot->importe,
            ])->values(),
        ]);
    }

    /**
     * POST remitos/vincular-factura
     *
     * Deja asentados los remitos que cubre una factura. Lo llama el front
     * INMEDIATAMENTE después de emitir la factura: no se engancha dentro del
     * camino de emisión de ARCA a propósito, para no tocar esa parte.
     *
     * `importe` dice cuánto de cada remito cubre esta factura, que es lo que
     * permite la facturación parcial (un remito repartido entre varias facturas).
     */
    public function vincularFactura(Request $request): JsonResponse
    {
        $empresa = $this->empresa($request, required: true);

        $datos = $request->validate([
            'saleInvoiceId' => ['required', 'integer', 'exists:sale_invoices,id'],
            'vinculos' => ['present', 'array'],
            'vinculos.*.remitoId' => ['required', 'integer', 'exists:remitos,id'],
            'vinculos.*.importe' => ['required', 'numeric', 'min:0'],
        ], [], [
            'saleInvoiceId' => 'factura',
            'vinculos' => 'remitos',
            'vinculos.*.remitoId' => 'remito',
            'vinculos.*.importe' => 'importe',
        ]);

        $factura = SaleInvoices::find($datos['saleInvoiceId']);

        if (! $factura || (int) $factura->company_id !== (int) $empresa->id) {
            abort(404, 'La factura no existe.');
        }

        foreach ($datos['vinculos'] as $vinculo) {
            $remito = Remito::find($vinculo['remitoId']);

            // Mismo control que en el resto de la casa: el remito tiene que ser de
            // esta empresa y del mismo cliente que la factura, y estar emitido.
            if ((int) $remito->company_id !== (int) $empresa->id) {
                abort(404, 'El remito no existe.');
            }

            if ((int) $remito->customer_id !== (int) $factura->customer_id) {
                throw ValidationException::withMessages([
                    'vinculos' => 'Los remitos tienen que ser del mismo cliente que la factura.',
                ]);
            }

            if ($remito->estado !== RemitoService::ESTADO_EMITIDO) {
                throw ValidationException::withMessages([
                    'vinculos' => "El remito {$remito->number} no está emitido: no se puede facturar.",
                ]);
            }
        }

        $this->service->sincronizarFacturasDeLaFactura($factura, $datos['vinculos']);

        return response()->json([
            'data' => $factura->fresh('remitos')->remitos->map(fn (Remito $remito) => [
                'id' => $remito->id,
                'numero' => $this->service->numeroCompleto($remito),
                'importe' => (float) $remito->pivot->importe,
            ])->values(),
        ]);
    }

    /**
     * POST remitos/{remito}/emitir
     *
     * Le asigna número, congela el snapshot y lo pasa a emitido.
     */
    public function emitir(Request $request, Remito $remito): JsonResponse
    {
        $this->autorizar($request, $remito);

        $remito = $this->service->emitir($remito);

        return response()->json(['data' => $this->presentar($remito)]);
    }

    /**
     * POST remitos/{remito}/anular
     */
    public function anular(Request $request, Remito $remito): JsonResponse
    {
        $this->autorizar($request, $remito);

        $datos = $request->validate([
            'motivo' => ['required', 'string', 'max:191'],
        ], [], ['motivo' => 'motivo de la anulación']);

        $remito = $this->service->anular($remito, $datos['motivo']);

        return response()->json(['data' => $this->presentar($remito)]);
    }

    /**
     * GET remitos/{remito}/pdf
     *
     * Devuelve TODO resuelto para dibujar el remito. Si ya está emitido sale del
     * snapshot congelado, así la reimpresión es idéntica a la original.
     */
    public function pdf(Request $request, Remito $remito): JsonResponse
    {
        $this->autorizar($request, $remito);

        $remito->load(['items.product', 'customer.afipInscription', 'saleInvoices']);

        return response()->json(['data' => $this->service->datosParaPdf($remito)]);
    }

    // ------------------------------------------------------------------ privados

    private function empresa(Request $request, bool $required = false): ?Company
    {
        $usuario = $request->user();

        $empresa = $request->input('company_id')
            ? $usuario->companies()->find($request->input('company_id'))
            : $usuario->companies()->first();

        if ($required && ! $empresa instanceof Company) {
            throw ValidationException::withMessages([
                'company_id' => 'No hay una empresa asociada al usuario.',
            ]);
        }

        return $empresa;
    }

    private function autorizar(Request $request, Remito $remito): ?Company
    {
        $empresa = $request->user()->companies()->find($remito->company_id);

        if (! $empresa instanceof Company) {
            abort(404, 'El remito no existe.');
        }

        return $empresa;
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'customerId' => ['required', 'integer', 'exists:customers,id'],
            'fechaEntrega' => ['required', 'date'],
            'direccionEntrega' => ['nullable', 'string', 'max:500'],
            'saleConditionId' => ['nullable', 'integer', 'exists:sale_conditions,id'],
            'paymentTypeId' => ['nullable', 'integer'],
            'statusId' => ['nullable', 'integer'],
            'referenciaComercial' => ['nullable', 'string', 'max:191'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'transportista' => ['nullable', 'array'],
            'transportista.nombre' => ['nullable', 'string', 'max:191'],
            'transportista.cuit' => ['nullable', 'string', 'max:20'],
            'transportista.domicilio' => ['nullable', 'string', 'max:191'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.productId' => ['required', 'integer', 'exists:products,id'],
            'items.*.cantidad' => ['required', 'numeric', 'min:0.01'],
            'items.*.precioUnitario' => ['required', 'numeric', 'min:0'],
        ], [], [
            'customerId' => 'cliente',
            'fechaEntrega' => 'fecha de entrega',
            'items' => 'bienes trasladados',
            'items.*.productId' => 'producto',
            'items.*.cantidad' => 'cantidad',
            'items.*.precioUnitario' => 'precio unitario',
        ]);
    }

    /**
     * Da forma a la respuesta que consume la pantalla.
     */
    private function presentar(Remito $remito): \Illuminate\Support\Collection
    {
        $cliente = $remito->customer;

        return collect([
            'id' => $remito->id,
            'numero' => $this->service->numeroCompleto($remito),
            'numeroRemito' => $remito->number ? str_pad((string) $remito->number, 8, '0', STR_PAD_LEFT) : null,
            'puntoVenta' => $remito->pto_vta ? (int) $remito->pto_vta : null,
            'clase' => $remito->clase,
            'estado' => $remito->estado,
            'fechaEmision' => optional($remito->emitido_at)->format('Y-m-d'),
            'fechaEntrega' => $remito->delivery_date,
            'cliente' => [
                'id' => $cliente->id ?? null,
                'razonSocial' => trim(($cliente->name ?? '').' '.($cliente->last_name ?? '')),
                'cuit' => $cliente->afip_number ?? null,
            ],
            'total' => round((float) $remito->total, 2),
            'importeFacturado' => round($remito->importeFacturado(), 2),
            'importePendiente' => round($remito->importePendiente(), 2),
            'referenciaComercial' => $remito->commercial_reference,

            // Sólo tienen sentido en el detalle, pero van siempre para que el
            // front use una sola forma de respuesta.
            'direccionEntrega' => $remito->direccion_entrega,
            'condicionVenta' => optional($remito->saleCondition)->name,
            'saleConditionId' => $remito->sales_condition_id,
            'paymentTypeId' => $remito->payment_type_id,
            'observaciones' => $remito->observaciones,
            'transportista' => $remito->transportista_nombre
                ? [
                    'nombre' => $remito->transportista_nombre,
                    'cuit' => $remito->transportista_cuit,
                    'domicilio' => $remito->transportista_domicilio,
                ]
                : null,
            'items' => $remito->relationLoaded('items')
                ? $remito->items->map(fn ($item) => [
                    'id' => $item->id,
                    'productoId' => (int) $item->product_id,
                    'descripcion' => optional($item->product)->name ?? '',
                    'cantidad' => (float) $item->quantity,
                    'precioUnitario' => (float) $item->neto,
                    'total' => (float) $item->total,
                ])->values()
                : collect(),
            'facturas' => $remito->relationLoaded('saleInvoices')
                ? $remito->saleInvoices->map(fn (SaleInvoices $factura) => [
                    'id' => $factura->id,
                    'numero' => str_pad((string) $factura->pto_vta, 5, '0', STR_PAD_LEFT)
                        .'-'.str_pad((string) $factura->cbte_desde, 8, '0', STR_PAD_LEFT),
                    'fecha' => optional($factura->cbte_fch)->format('d/m/Y'),
                    'importe' => (float) $factura->pivot->importe,
                ])->values()
                : collect(),
            'emitidoAt' => optional($remito->emitido_at)->toIso8601String(),
            'anuladoAt' => optional($remito->anulado_at)->toIso8601String(),
            'anuladoMotivo' => $remito->anulado_motivo,
        ]);
    }
}
