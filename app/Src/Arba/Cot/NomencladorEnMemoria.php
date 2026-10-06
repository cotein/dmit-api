<?php

namespace App\Src\Arba\Cot;

/**
 * Nomenclador en memoria, para tests y para validar un lote antes de importarlo.
 *
 * Tener las dos implementaciones detrás de la misma interfaz es lo que permite
 * que el validador del TXT se pruebe sin base de datos.
 */
final class NomencladorEnMemoria implements CotNomenclador
{
    /**
     * @param  array<int, string>  $codigos
     */
    public function __construct(private array $codigos = [])
    {
        // Se normaliza al construir y no al consultar: si no, un código cargado
        // con espacios desde una planilla nunca coincidiría.
        $this->codigos = array_map(fn ($codigo) => trim((string) $codigo), $this->codigos);
    }

    /**
     * @param  array<int, array{codigo: string}>  $filas
     */
    public static function desdeFilas(array $filas): self
    {
        return new self(array_column($filas, 'codigo'));
    }

    public function existeProducto(string $codigo): bool
    {
        return in_array(trim($codigo), $this->codigos, true);
    }
}
