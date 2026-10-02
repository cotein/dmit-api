<?php

declare(strict_types=1);

namespace App\Src\Afip;

/**
 * Respuesta de ARCA para FECAESolicitar (WSFE v1), ya normalizada.
 *
 * ARCA puede contestar de tres maneras (manual WSFE, "Descripción del proceso"):
 *  1. aprueba el comprobante y asigna CAE;
 *  2. lo aprueba CON OBSERVACIONES (validaciones no excluyentes) y también asigna CAE;
 *  3. lo rechaza (validaciones excluyentes, por ejemplo el 10245 de la RG 5616) y no hay CAE.
 *
 * En los tres casos el usuario tiene que poder leer qué contestó ARCA, así que acá se junta
 * todo (resultado, CAE, observaciones, errores y eventos) en un solo objeto.
 */
final class ArcaResponse
{
    public const APROBADO = 'A';

    public const RECHAZADO = 'R';

    /** @var array<string, mixed> */
    private $raw;

    private string $resultado = '';

    private ?string $cae = null;

    private ?string $caeFchVto = null;

    private ?int $cbteDesde = null;

    private ?int $cbteHasta = null;

    /** @var array<int, array{code: int|string|null, msg: string}> */
    private array $observaciones = [];

    /** @var array<int, array{code: int|string|null, msg: string}> */
    private array $errores = [];

    /** @var array<int, array{code: int|string|null, msg: string}> */
    private array $eventos = [];

    /**
     * @param  array<string, mixed>  $raw  Respuesta cruda (FECAESolicitarResult o el response completo).
     */
    private function __construct(array $raw)
    {
        $this->raw = $raw;

        // La respuesta puede venir envuelta o no según el cliente SOAP.
        $respuesta = $raw['FECAESolicitarResult'] ?? $raw;

        $cabezal = is_array($respuesta['FeCabResp'] ?? null) ? $respuesta['FeCabResp'] : [];
        $detalle = self::primero(self::lista($respuesta['FeDetResp'] ?? [], 'FECAEDetResponse'));

        $this->resultado = strtoupper((string) ($detalle['Resultado'] ?? $cabezal['Resultado'] ?? ''));

        $this->cbteDesde = self::entero($detalle['CbteDesde'] ?? null);
        $this->cbteHasta = self::entero($detalle['CbteHasta'] ?? null);

        $this->cae = self::texto($detalle['CAE'] ?? null);
        $this->caeFchVto = self::texto($detalle['CAEFchVto'] ?? null);

        $this->observaciones = self::codigos(self::lista($detalle['Observaciones'] ?? [], 'Obs'));
        $this->errores = self::codigos(self::lista($respuesta['Errors'] ?? [], 'Err'));
        $this->eventos = self::codigos(self::lista($respuesta['Events'] ?? [], 'Evt'));
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public static function fromFecaesolicitarResult(array $result): self
    {
        return new self($result);
    }

    /**
     * Sólo se considera autorizado el comprobante que ARCA marcó como aprobado: cualquier otro
     * resultado (rechazado, parcial, observado sin CAE) se trata como no autorizado.
     */
    public function isApproved(): bool
    {
        return $this->resultado === self::APROBADO && $this->errores === [];
    }

    public function isRejected(): bool
    {
        return ! $this->isApproved();
    }

    public function hasObservaciones(): bool
    {
        return $this->observaciones !== [];
    }

    public function hasEventos(): bool
    {
        return $this->eventos !== [];
    }

    public function resultado(): string
    {
        return $this->resultado;
    }

    public function cae(): ?string
    {
        return $this->cae;
    }

    public function caeFchVto(): ?string
    {
        return $this->caeFchVto;
    }

    public function cbteDesde(): ?int
    {
        return $this->cbteDesde;
    }

    public function cbteHasta(): ?int
    {
        return $this->cbteHasta;
    }

    /**
     * Todo lo que ARCA dijo, en texto, para mostrarle al usuario.
     *
     * @return array<int, string>
     */
    public function mensajes(): array
    {
        $mensajes = [];

        foreach ($this->errores as $error) {
            $mensajes[] = self::linea($error);
        }

        foreach ($this->observaciones as $observacion) {
            $mensajes[] = self::linea($observacion);
        }

        foreach ($this->eventos as $evento) {
            $mensajes[] = self::linea($evento);
        }

        return $mensajes;
    }

    /**
     * Una sola línea con lo esencial, para el mensaje de error.
     */
    public function summary(): string
    {
        $mensajes = $this->mensajes();

        if ($mensajes === []) {
            return 'ARCA no autorizó el comprobante (resultado "' . ($this->resultado !== '' ? $this->resultado : 'sin dato') . '").';
        }

        return 'ARCA rechazó el comprobante: ' . implode(' | ', $mensajes);
    }

    /**
     * Respuesta pública para el frontend: es la que el usuario tiene que ver siempre.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'resultado' => $this->resultado,
            'aprobado' => $this->isApproved(),
            'cae' => $this->cae,
            'cae_fch_vto' => $this->caeFchVto,
            'cbte_desde' => $this->cbteDesde,
            'cbte_hasta' => $this->cbteHasta,
            'observaciones' => $this->observaciones,
            'errores' => $this->errores,
            'eventos' => $this->eventos,
            'mensajes' => $this->mensajes(),
            'raw' => $this->raw,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function raw(): array
    {
        return $this->raw;
    }

    /**
     * ARCA puede devolver un nodo con un solo elemento como objeto suelto o como lista.
     *
     * @param  mixed  $nodo
     * @return array<int, array<string, mixed>>
     */
    private static function lista($nodo, string $clave): array
    {
        if (! is_array($nodo)) {
            return [];
        }

        $valor = $nodo[$clave] ?? $nodo;

        if (! is_array($valor)) {
            return [];
        }

        if ($valor === []) {
            return [];
        }

        $claves = array_keys($valor);

        if ($claves === range(0, count($valor) - 1)) {
            return array_values(array_filter($valor, 'is_array'));
        }

        return [$valor];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array{code: int|string|null, msg: string}>
     */
    private static function codigos(array $items): array
    {
        $resultado = [];

        foreach ($items as $item) {
            $mensaje = self::texto($item['Msg'] ?? null);

            if ($mensaje === null) {
                continue;
            }

            $codigo = $item['Code'] ?? null;

            $resultado[] = [
                'code' => is_int($codigo) || is_string($codigo) ? $codigo : null,
                'msg' => $mensaje,
            ];
        }

        return $resultado;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private static function linea(array $item): string
    {
        $codigo = $item['code'] ?? null;

        return $codigo !== null ? $codigo . ': ' . $item['msg'] : $item['msg'];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private static function primero(array $items): array
    {
        return $items[0] ?? [];
    }

    /**
     * @param  mixed  $valor
     */
    private static function texto($valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $texto = trim((string) $valor);

        return $texto === '' ? null : $texto;
    }

    /**
     * @param  mixed  $valor
     */
    private static function entero($valor): ?int
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return (int) $valor;
    }
}
