<?php

namespace App\Src\Arba\Cot;

use App\Models\ArbaNomenclador;

/**
 * Implementación del nomenclador contra la tabla arba_nomenclador_tb.
 */
final class NomencladorArba implements CotNomenclador
{
    /**
     * Se cachean las consultas del último lote: un archivo con veinte remitos y
     * cuarenta productos distintos no tiene por qué pegarle a la base cuarenta
     * veces por el mismo código.
     *
     * @var array<string, bool>
     */
    private array $cache = [];

    public function existeProducto(string $codigo): bool
    {
        $codigo = trim($codigo);

        if ($codigo === '') {
            return false;
        }

        return $this->cache[$codigo] ??= ArbaNomenclador::query()
            ->productos()
            ->where('codigo', $codigo)
            ->exists();
    }

    /**
     * Descripción del nomenclador para un código, para pre-cargar la pantalla.
     */
    public function descripcionDe(string $codigo): ?string
    {
        return ArbaNomenclador::query()->where('codigo', trim($codigo))->value('descripcion');
    }

    /**
     * Unidad de medida que ARBA propone para ese producto.
     */
    public function unidadMedidaDe(string $codigo): ?string
    {
        return ArbaNomenclador::query()->productos()->where('codigo', trim($codigo))->value('unidad_medida');
    }
}
