<?php

namespace App\Src\Arba\Cot;

/**
 * El resultado de presentar un archivo de remitos a ARBA.
 *
 * Son dos respuestas distintas, según el instructivo:
 *
 *   <TBCOMPROBANTE>  la transacción se aceptó; adentro trae el resultado de
 *                    CADA remito y el número de comprobante que identifica al
 *                    archivo entero.
 *   <TBError>        la transacción falló entera (usuario o clave mal, archivo
 *                    con nombre incorrecto, etc.) y NO hay que mirar los remitos.
 *
 * Que la transacción sea aceptada no significa que los remitos lo sean: por eso
 * `exitoso` habla de la transacción y el estado de cada remito se consulta aparte.
 */
final class CotResultado
{
    /**
     * @param  array<string, CotResultadoRemito>  $remitos  indexado por numeroUnico
     */
    public function __construct(
        public readonly bool $exitoso,
        public readonly ?string $numeroComprobante = null,
        public readonly ?string $codigoIntegridad = null,
        public readonly ?string $nombreArchivo = null,
        public readonly ?string $cuitEmpresa = null,
        public readonly array $remitos = [],
        public readonly ?string $errorTipo = null,
        public readonly ?string $errorCodigo = null,
        public readonly ?string $errorMensaje = null,
        public readonly ?string $cuerpoCrudo = null,
    ) {
    }

    /**
     * Error de transacción: la respuesta fue un TBError.
     */
    public static function error(string $codigo, string $mensaje, ?string $tipo = null, ?string $cuerpo = null): self
    {
        return new self(
            exitoso: false,
            errorTipo: $tipo,
            errorCodigo: $codigo,
            errorMensaje: $mensaje,
            cuerpoCrudo: $cuerpo,
        );
    }

    public function remito(string $numeroUnico): ?CotResultadoRemito
    {
        return $this->remitos[$numeroUnico] ?? null;
    }

    /**
     * @return CotResultadoRemito[]
     */
    public function remitosProcesados(): array
    {
        return array_values(array_filter($this->remitos, fn (CotResultadoRemito $r) => $r->procesado));
    }

    /**
     * @return CotResultadoRemito[]
     */
    public function remitosRechazados(): array
    {
        return array_values(array_filter(
            $this->remitos,
            fn (CotResultadoRemito $r) => ! $r->procesado && ! $r->yaEstabaEmitido()
        ));
    }

    /**
     * @return CotResultadoRemito[]
     */
    public function remitosYaEmitidos(): array
    {
        return array_values(array_filter($this->remitos, fn (CotResultadoRemito $r) => $r->yaEstabaEmitido()));
    }

    public function todosProcesados(): bool
    {
        return $this->exitoso && $this->remitosRechazados() === [];
    }

    public function resumen(): string
    {
        if (! $this->exitoso) {
            return 'ARBA rechazó la transacción'
                . ($this->errorCodigo ? " ({$this->errorCodigo})" : '')
                . ($this->errorMensaje ? ': ' . $this->errorMensaje : '');
        }

        return sprintf(
            'Comprobante %s: %d procesados, %d ya emitidos, %d rechazados',
            $this->numeroComprobante ?? '(sin número)',
            count($this->remitosProcesados()),
            count($this->remitosYaEmitidos()),
            count($this->remitosRechazados())
        );
    }
}
