<?php

namespace App\Src\Arba\Cot;

/**
 * Normalización de números para el archivo TXT del COT.
 *
 * El diseño de ARBA pide cantidades e importes "con valores decimales
 * obligatorios": no se manda el punto decimal, se mandan los dígitos con los dos
 * decimales pegados. Si la cantidad es 200, el campo viaja como 20000; si es
 * 1,5, viaja como 150.
 *
 * Está aislado en una clase propia porque es la fuente de error más silenciosa
 * de todo el formato: un punto decimal de más no rompe el archivo, lo hace
 * interpretar el importe dividido por cien.
 */
final class CotNumber
{
    /**
     * Dígitos con decimales implícitos, sin separador.
     *
     * 200      -> "20000"
     * 1.5      -> "150"
     * 0.01     -> "001"
     */
    public static function decimal($value, int $decimals = 2): string
    {
        return number_format((float) $value, $decimals, '', '');
    }

    /**
     * ¿El valor entra en un campo de $integerDigits enteros y $decimalDigits
     * decimales sin perder precisión?
     */
    public static function fits($value, int $integerDigits, int $decimalDigits = 2): bool
    {
        $value = (float) $value;

        if ($value < 0) {
            return false;
        }

        $max = (float) str_repeat('9', $integerDigits) + (1 - pow(10, -$decimalDigits));

        return $value <= $max;
    }
}
