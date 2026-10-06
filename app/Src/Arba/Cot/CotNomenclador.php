<?php

namespace App\Src\Arba\Cot;

/**
 * Lo único que el validador necesita saber del nomenclador de ARBA.
 *
 * Está como interfaz para que CotValidator siga siendo una clase pura, sin base
 * de datos: en producción se le pasa la implementación que consulta la tabla, y
 * en los tests una implementación en memoria. Si el validador dependiera
 * directamente de Eloquent, cada test del generador del TXT tendría que levantar
 * la base.
 */
interface CotNomenclador
{
    /**
     * ¿El código de producto existe en el nomenclador de ARBA y es del nivel que
     * va al registro 03?
     */
    public function existeProducto(string $codigo): bool;
}
