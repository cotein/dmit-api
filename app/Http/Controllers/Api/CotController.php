<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ArbaNomenclador;
use App\Models\Company;
use App\Models\CompanyTrasladoPoint;
use App\Models\Cot;
use App\Models\CotArchivo;
use App\Models\CotItem;
use App\Models\CotMapeoUnidadNomenclador;
use App\Models\CotProvincia;
use App\Src\Arba\Cot\CotEmisionService;
use App\Src\Arba\Cot\CotFileName;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Endpoints del COT (Código de Operación de Traslado) de ARBA.
 *
 * El COT es una entidad propia y previa al traslado: NUNCA se genera desde una
 * factura. La factura es el respaldo y se referencia por id interno.
 *
 * La emisión es ENCOLADA con estado: el endpoint arma el archivo, lo presenta y
 * devuelve el COT con su estado. Un COT ya emitido NO se edita: se anula y se
 * crea uno nuevo.
 */
class CotController extends Controller
{
    private const ESTADO_BORRADOR = 'borrador';

    private const ESTADO_EMITIDO = 'emitido';

    private const ESTADO_RECHAZADO = 'rechazado';

    private const ESTADO_ANULADO = 'anulado';

    public function __construct(private readonly CotEmisionService $emision)
    {
    }

    /**
     * GET arba/cot/catalogos
     *
     * Lo que la pantalla necesita para llenar los selects. El nomenclador se
     * devuelve acotado y con búsqueda: son miles de variedades.
     */
    public function catalogos(Request $request): JsonResponse
    {
        $empresa = $this->empresa($request);

        $busqueda = trim((string) $request->query('search', ''));

        // El campo nivel NO es la profundidad del árbol: los productos que van
        // al TXT son las variedades de 6 dígitos, sin importar el nivel.
        $nomenclador = ArbaNomenclador::query()
            ->whereRaw('CHAR_LENGTH(codigo) = 6')
            ->when($busqueda !== '', function ($query) use ($busqueda) {
                $query->where(function ($sub) use ($busqueda) {
                    $sub->where('codigo', 'like', $busqueda.'%')
                        ->orWhere('descripcion', 'like', '%'.$busqueda.'%');
                });
            })
            ->orderBy('codigo')
            ->limit((int) min(max((int) $request->query('limit', 200), 1), 500))
            ->get(['codigo', 'descripcion', 'unidad_medida']);

        $puntos = $empresa
            ? CompanyTrasladoPoint::query()->where('company_id', $empresa->id)->get(['id', 'nombre'])
            : collect();

        return response()->json([
            'provincias' => CotProvincia::query()->orderBy('codigo')->get(['codigo', 'descripcion']),
            'puntosTraslado' => $puntos,
            'nomenclador' => $nomenclador,
        ]);
    }

    /**
     * GET arba/cot
     */
    public function index(Request $request): JsonResponse
    {
        $empresa = $this->empresa($request);

        $cots = Cot::query()
            ->with('items')
            ->when($empresa, fn ($query) => $query->where('company_id', $empresa->id))
            ->when($request->query('desde'), fn ($query, $desde) => $query->where('fecha_emision', '>=', $desde))
            ->when($request->query('hasta'), fn ($query, $hasta) => $query->where('fecha_emision', '<=', $hasta))
            ->when($request->query('destinatario'), function ($query, $destinatario) {
                $query->where('destinatario_razon_social', 'like', '%'.$destinatario.'%');
            })
            ->orderByDesc('id')
            ->paginate((int) min(max((int) $request->query('per_page', 20), 1), 100));

        return response()->json($cots);
    }

    /**
     * POST arba/cot
     */
    public function store(Request $request): JsonResponse
    {
        $empresa = $this->empresa($request, required: true);

        $datos = $this->validarBorrador($request);

        $cot = DB::transaction(function () use ($datos, $empresa, $request) {
            $cot = new Cot();
            $this->completarDesdePayload($cot, $datos, $empresa, $request);
            $cot->save();

            $this->guardarItems($cot, $datos['productos']);

            return $cot;
        });

        return response()->json($this->presentar($cot->fresh('items')), 201);
    }

    /**
     * GET arba/cot/{cot}
     */
    public function show(Request $request, Cot $cot): JsonResponse
    {
        $this->autorizar($request, $cot);

        return response()->json($this->presentar($cot->load('items')));
    }

    /**
     * PUT arba/cot/{cot}
     *
     * Sólo sobre un borrador. Un COT emitido se anula y se vuelve a crear.
     */
    public function update(Request $request, Cot $cot): JsonResponse
    {
        $empresa = $this->autorizar($request, $cot);

        if ($cot->procesado) {
            throw ValidationException::withMessages([
                'cot' => 'El COT ya fue emitido: para corregirlo hay que anularlo y crear uno nuevo.',
            ]);
        }

        $datos = $this->validarBorrador($request);

        DB::transaction(function () use ($cot, $datos, $empresa, $request) {
            $this->completarDesdePayload($cot, $datos, $empresa, $request);
            $cot->save();

            $cot->items()->delete();
            $this->guardarItems($cot, $datos['productos']);
        });

        return response()->json($this->presentar($cot->fresh('items')));
    }

    /**
     * POST arba/cot/{cot}/emitir
     *
     * Arma el TXT, lo presenta a ARBA y guarda el resultado.
     */
    public function emitir(Request $request, Cot $cot): JsonResponse
    {
        $empresa = $this->autorizar($request, $cot);

        if ($cot->procesado) {
            throw ValidationException::withMessages([
                'cot' => 'El COT ya fue emitido.',
            ]);
        }

        if (! $empresa instanceof Company) {
            throw ValidationException::withMessages([
                'company' => 'El COT no tiene una empresa asociada.',
            ]);
        }

        $archivo = new CotArchivo();
        $archivo->company_id = $empresa->id;
        $archivo->user_id = $request->user()->id;
        $archivo->company_traslado_point_id = $request->input('puntoTrasladoId');
        $archivo->cuit_empresa = $this->cuitDe($empresa);
        $archivo->planta = str_pad((string) $request->input('planta', '001'), 3, '0', STR_PAD_LEFT);
        $archivo->puerta = str_pad((string) $request->input('puerta', $cot->codigo_unico ?: '001'), 3, '0', STR_PAD_LEFT);
        $archivo->fecha_archivo = $cot->fecha_emision;
        $archivo->secuencia = $this->proximaSecuencia($empresa, $cot);
        $archivo->cantidad_remitos = 1;
        $archivo->estado = CotArchivo::ESTADO_PENDIENTE;
        $archivo->nombre_archivo = CotFileName::build(
            $archivo->cuit_empresa,
            $archivo->planta,
            $archivo->puerta,
            $cot->fecha_emision,
            $archivo->secuencia
        );
        $archivo->save();

        $cot->cot_archivo_id = $archivo->id;
        $cot->save();

        $resultado = $this->emision->emitir($archivo->fresh());

        $remito = $resultado->remito($cot->codigo_unico);

        if ($resultado->exitoso && ($remito === null || $remito->procesado)) {
            $cot->procesado = true;
            $cot->numero_cot = $remito?->numeroCot;
            $cot->errores = null;
        } else {
            $cot->procesado = false;
            $cot->errores = $remito?->descripcionDeErrores()
                ?? $resultado->errorMensaje
                ?? 'ARBA no procesó el remito.';
        }

        $cot->save();

        return response()->json($this->presentar($cot->fresh('items')));
    }

    /**
     * GET arba/cot/{cot}/archivo
     *
     * Descarga el TXT tal como se envió.
     */
    public function archivo(Request $request, Cot $cot): Response
    {
        $this->autorizar($request, $cot);

        $archivo = $cot->archivo;

        if (! $archivo instanceof CotArchivo || $archivo->contenido === null) {
            abort(404, 'El COT todavía no tiene un archivo generado.');
        }

        return response($archivo->contenido, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$archivo->nombre_archivo.'"',
        ]);
    }

    /**
     * POST arba/cot/{cot}/anular
     */
    public function anular(Request $request, Cot $cot): JsonResponse
    {
        $this->autorizar($request, $cot);

        if ($cot->procesado) {
            throw ValidationException::withMessages([
                'cot' => 'El COT ya fue presentado a ARBA: se corrige con una operación de anulación, no desde acá.',
            ]);
        }

        $cot->errores = 'Anulado por el usuario.';
        $cot->save();

        return response()->json($this->presentar($cot->fresh('items'))->merge(['estado' => self::ESTADO_ANULADO]));
    }

    /**
     * Empresa con la que se opera: la pedida, o la primera del usuario.
     */
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

    private function autorizar(Request $request, Cot $cot): ?Company
    {
        $empresa = $request->user()->companies()->find($cot->company_id);

        if (! $empresa instanceof Company) {
            abort(404, 'El COT no existe.');
        }

        return $empresa;
    }

    /**
     * @return array<string, mixed>
     */
    private function validarBorrador(Request $request): array
    {
        return $request->validate([
            'sujetoGenerador' => 'required|in:E,D',
            'fechaEmision' => 'required|string|max:10',
            'fechaSalidaTransporte' => 'required|string|max:10',
            'puntoVenta' => 'required|string|max:3',
            'numeroRemito' => 'required|string|max:16',
            'puntoTrasladoId' => 'nullable|integer',
            'codigo' => 'nullable|string|max:16',
            'origen' => 'required|array',
            'origen.provincia' => 'required|string|size:1|exists:cot_provincias,codigo',
            'origen.localidad' => 'required|string|max:50',
            'origen.domicilio' => 'required|string|max:40',
            'origen.codigoPostal' => 'nullable|string|max:8',
            'destino' => 'required|array',
            'destino.cuit' => 'required|string|max:11',
            'destino.razonSocial' => 'required|string|max:50',
            'destino.provincia' => 'required|string|size:1|exists:cot_provincias,codigo',
            'destino.localidad' => 'required|string|max:50',
            'destino.domicilio' => 'required|string|max:40',
            'destino.codigoPostal' => 'nullable|string|max:8',
            'productos' => 'required|array|min:1',
            'productos.*.codigo' => 'required|string|size:6|exists:arba_nomenclador_tb,codigo',
            'productos.*.cantidad' => 'required',
            'productos.*.importe' => 'nullable',
            'facturaId' => 'nullable|integer',
        ]);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function completarDesdePayload(Cot $cot, array $datos, ?Company $empresa, Request $request): void
    {
        $cot->company_id = $empresa?->id;
        $cot->sale_invoice_id = $datos['facturaId'] ?? null;
        $cot->sujeto_generador = $datos['sujetoGenerador'];
        $cot->codigo_unico = $datos['codigo'] ?? $datos['numeroRemito'];
        $cot->fecha_emision = $datos['fechaEmision'];
        $cot->fecha_salida_transporte = $datos['fechaSalidaTransporte'];

        $cot->origen_domicilio_provincia = $datos['origen']['provincia'];
        $cot->origen_domicilio_localidad = $datos['origen']['localidad'];
        $cot->origen_domicilio_calle = $datos['origen']['domicilio'];
        $cot->origen_domicilio_codigo_postal = $datos['origen']['codigoPostal'] ?? null;
        $cot->origen_cuit = $this->cuitDe($empresa);

        $cot->destinatario_cuit = $datos['destino']['cuit'];
        $cot->destinatario_razon_social = $datos['destino']['razonSocial'];
        $cot->destino_domicilio_provincia = $datos['destino']['provincia'];
        $cot->destino_domicilio_localidad = $datos['destino']['localidad'];
        $cot->destino_domicilio_calle = $datos['destino']['domicilio'];
        $cot->destino_domicilio_codigo_postal = $datos['destino']['codigoPostal'] ?? null;

        $cot->importe = $this->sumarImportes($datos['productos']);
    }

    /**
     * @param  array<int, array<string, mixed>>  $productos
     */
    private function guardarItems(Cot $cot, array $productos): void
    {
        foreach ($productos as $producto) {
            $nomenclador = ArbaNomenclador::find($producto['codigo']);

            $item = new CotItem();
            $item->cot_id = $cot->id;
            $item->codigo_unico_producto = $producto['codigo'];
            $item->arba_codigo_unidad_medida = $this->codigoUnidad($nomenclador?->unidad_medida);
            $item->propio_descripcion_producto = $nomenclador?->descripcion;
            $item->propio_descripcion_unidad_medida = $nomenclador?->unidad_medida;
            $item->cantidad = $this->aDecimal($producto['cantidad']);
            $item->cantidad_ajustada = $this->aDecimal($producto['cantidad']);
            $item->save();
        }
    }

    /**
     * El código de ARBA es de UN carácter. La unidad del nomenclador es
     * descriptiva, así que sólo se toma cuando ya viene en ese formato.
     */
    private function codigoUnidad(?string $unidad): ?string
    {
        $unidad = trim((string) $unidad);

        if ($unidad === '') {
            return null;
        }

        // La unidad del nomenclador es descriptiva ("KILOGRAMO"): se traduce
        // con el mapeo, que es lo que ARBA espera en un solo carácter.
        $codigo = CotMapeoUnidadNomenclador::codigoPara($unidad);

        if ($codigo !== null) {
            return $codigo;
        }

        // Si la unidad ya viene como un carácter suelto, se usa tal cual.
        return strlen($unidad) === 1 ? $unidad : null;
    }

    private function aDecimal(mixed $valor): float
    {
        $limpio = str_replace('.', '', (string) $valor);
        $limpio = str_replace(',', '.', $limpio);

        return (float) $limpio;
    }

    /**
     * @param  array<int, array<string, mixed>>  $productos
     */
    private function sumarImportes(array $productos): float
    {
        return array_reduce(
            $productos,
            fn (float $acc, array $producto) => $acc + $this->aDecimal($producto['importe'] ?? 0),
            0.0
        );
    }

    private function cuitDe(?Company $empresa): ?string
    {
        return $empresa?->cuit ? preg_replace('/\D/', '', $empresa->cuit) : null;
    }

    private function proximaSecuencia(Company $empresa, Cot $cot): string
    {
        $usados = CotArchivo::query()
            ->where('company_id', $empresa->id)
            ->where('fecha_archivo', $cot->fecha_emision)
            ->count();

        return str_pad((string) ($usados + 1), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Da forma a la respuesta que consume la pantalla.
     */
    private function presentar(Cot $cot): \Illuminate\Support\Collection
    {
        return collect([
            'id' => $cot->id,
            'estado' => $cot->procesado ? self::ESTADO_EMITIDO : self::ESTADO_BORRADOR,
            'sujetoGenerador' => $cot->sujeto_generador,
            'fechaEmision' => optional($cot->fecha_emision)->format('Ymd'),
            'fechaSalidaTransporte' => optional($cot->fecha_salida_transporte)->format('Ymd'),
            'numeroRemito' => $cot->codigo_unico,
            'destinatarioRazonSocial' => $cot->destinatario_razon_social,
            'importe' => $cot->importe,
            'numeroCot' => $cot->numero_cot,
            'archivoNombre' => $cot->archivo?->nombre_archivo,
            'errores' => $cot->errores ? [$cot->errores] : [],
            'productos' => $cot->items->map(fn (CotItem $item) => [
                'codigo' => $item->codigo_unico_producto,
                'descripcion' => $item->propio_descripcion_producto,
                'unidad' => $item->arba_codigo_unidad_medida,
                'cantidad' => $item->cantidad,
            ])->values(),
        ]);
    }
}
