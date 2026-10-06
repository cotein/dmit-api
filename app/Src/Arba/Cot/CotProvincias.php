<?php

namespace App\Src\Arba\Cot;

/**
 * Tabla de provincias del COT de ARBA.
 *
 * El campo ORIGEN_DOMICILIO_PROVINCIA / DESTINO_DOMICILIO_PROVINCIA del registro
 * 02 es UN carácter alfabético. La validación 90 de ARBA exige además que al
 * menos una de las dos puntas del viaje sea 'B' (Buenos Aires).
 *
 * La tabla vive acá como constante porque es la única fuente de verdad: de acá
 * la toma el seeder que llena cot_provincias y de acá valida CotValidator sin
 * pegarle a la base. Si ARBA publicara una corrección, se cambia en un solo
 * lugar.
 *
 * Coincide con el sufijo del código ISO 3166-2 que ya está en states.codigo31662
 * (Buenos Aires = AR-B -> 'B', CABA = AR-C -> 'C'), que es lo que permite
 * pre-cargar la provincia desde un domicilio existente de la aplicación.
 */
final class CotProvincias
{
    public const PROVINCIAS = [
        'A' => 'Salta',
        'B' => 'Buenos Aires',
        'C' => 'Capital Federal',
        'D' => 'San Luis',
        'E' => 'Entre Ríos',
        'F' => 'La Rioja',
        'G' => 'Santiago del Estero',
        'H' => 'Chaco',
        'J' => 'San Juan',
        'K' => 'Catamarca',
        'L' => 'La Pampa',
        'M' => 'Mendoza',
        'N' => 'Misiones',
        'P' => 'Formosa',
        'Q' => 'Neuquén',
        'R' => 'Río Negro',
        'S' => 'Santa Fe',
        'T' => 'Tucumán',
        'U' => 'Chubut',
        'V' => 'Tierra del Fuego',
        'W' => 'Corrientes',
        'X' => 'Córdoba',
        'Y' => 'Jujuy',
        'Z' => 'Santa Cruz',
    ];

    /**
     * @return string[]
     */
    public static function codigos(): array
    {
        return array_keys(self::PROVINCIAS);
    }

    public static function existe(?string $codigo): bool
    {
        return array_key_exists(strtoupper(trim((string) $codigo)), self::PROVINCIAS);
    }

    public static function descripcion(?string $codigo): ?string
    {
        return self::PROVINCIAS[strtoupper(trim((string) $codigo))] ?? null;
    }
}
