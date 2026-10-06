<?php

namespace App\Src\Arba\Cot;

/**
 * El resultado que ARBA informa para UN remito del archivo.
 *
 * El instructivo es explícito en que "se procesarán en forma independiente los
 * remitos dentro del archivo recibido": un archivo puede salir con cinco remitos
 * aceptados y uno rechazado. Por eso el resultado se guarda por remito y no sólo
 * a nivel archivo.
 */
final class CotResultadoRemito
{
    /**
     * @param  array<int, array{codigo: string, descripcion: string}>  $errores
     */
    public function __construct(
        public readonly string $numeroUnico,
        public readonly bool $procesado,
        public readonly array $errores = [],
        public readonly ?string $numeroCot = null,
    ) {
    }

    /**
     * El "error" en realidad confirma que el remito ya estaba emitido: los
     * códigos 11 y 17 llegan como error pero significan que el envío es
     * idempotente. Tratarlos como fallo es el camino más rápido a un duplicado.
     */
    public function yaEstabaEmitido(): bool
    {
        if ($this->procesado || $this->errores === []) {
            return false;
        }

        foreach ($this->errores as $error) {
            if (! CotErrors::esExitoIdempotente($error['codigo'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return string[]
     */
    public function codigosDeError(): array
    {
        return array_map(fn (array $error) => $error['codigo'], $this->errores);
    }

    public function descripcionDeErrores(): string
    {
        return implode(' | ', array_map(
            fn (array $error) => $error['codigo'] . ': ' . $error['descripcion'],
            $this->errores
        ));
    }
}
