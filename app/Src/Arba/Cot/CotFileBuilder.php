<?php

namespace App\Src\Arba\Cot;

/**
 * Arma el archivo TXT de remitos electrónicos de ARBA.
 *
 * Estructura, según el instructivo:
 *
 *      - un único registro 01 (HEADER)
 *      - al menos un remito, compuesto por un registro 02 (REMITO) y al menos
 *        un registro 03 (PRODUCTOS)
 *      - un único registro 04 (FOOTER)
 *
 * Reglas de formato que no son obvias y que este builder resuelve:
 *
 *  - Los campos se separan con "|" y TODOS los registros terminan con fin de
 *    línea, incluido el último.
 *  - Las longitudes del diseño son máximas: no se rellena con blancos ni con
 *    ceros. Por eso acá no hay ningún str_pad salvo el del nombre del archivo.
 *  - Las cantidades y el importe van con dos decimales implícitos y sin
 *    separador (ver CotNumber).
 *  - Cada remito lleva sus registros 03 inmediatamente después de su 02.
 *
 * Ejemplo del instructivo, que los tests replican:
 *
 *      01|30111111118
 *      02|20060628|91 R000100000001|20060628|...
 *      03|1|3|2|...
 *      04|2
 *
 * El builder asume datos ya validados por CotValidator: no vuelve a validar ni
 * corrige. La única guarda que tiene es la forma del CUIT, que es un error de
 * programación y no un dato del usuario.
 */
final class CotFileBuilder
{
    public const REGISTRO_HEADER = '01';

    public const REGISTRO_REMITO = '02';

    public const REGISTRO_PRODUCTOS = '03';

    public const REGISTRO_FOOTER = '04';

    public const SEPARADOR = '|';

    /**
     * @param  CotRemitoData[]  $remitos
     */
    public function build(string $cuitEmpresa, array $remitos): string
    {
        return implode("\n", $this->buildLines($cuitEmpresa, $remitos)) . "\n";
    }

    /**
     * Las líneas del archivo, sin el fin de línea final.
     *
     * @param  CotRemitoData[]  $remitos
     * @return string[]
     */
    public function buildLines(string $cuitEmpresa, array $remitos): array
    {
        $cuitEmpresa = $this->normalizeCuit($cuitEmpresa);

        if (count($remitos) === 0) {
            throw new \InvalidArgumentException('El archivo necesita al menos un remito (registro 02).');
        }

        $lines = [$this->line(self::REGISTRO_HEADER, [$cuitEmpresa])];

        foreach ($remitos as $remito) {
            if (! $remito instanceof CotRemitoData) {
                throw new \InvalidArgumentException('Cada remito tiene que ser una instancia de CotRemitoData.');
            }

            $lines[] = $this->line(self::REGISTRO_REMITO, $remito->toRegistro());

            if (count($remito->items) === 0) {
                throw new \InvalidArgumentException('Cada remito necesita al menos un registro 03 de productos.');
            }

            foreach ($remito->items as $item) {
                $lines[] = $this->line(self::REGISTRO_PRODUCTOS, $item->toRegistro());
            }
        }

        $lines[] = $this->line(self::REGISTRO_FOOTER, [(string) count($remitos)]);

        return $lines;
    }

    /**
     * @param  string[]  $valores
     */
    private function line(string $tipoRegistro, array $valores): string
    {
        return implode(self::SEPARADOR, array_merge([$tipoRegistro], $valores));
    }

    private function normalizeCuit(string $cuit): string
    {
        $cuit = preg_replace('/\D/', '', $cuit);

        if (strlen($cuit) !== 11) {
            throw new \InvalidArgumentException('El CUIT de la empresa debe tener 11 dígitos.');
        }

        return $cuit;
    }
}
