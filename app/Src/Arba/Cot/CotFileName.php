<?php

namespace App\Src\Arba\Cot;

/**
 * Nombre del archivo de remitos y su relación con ARBA.
 *
 * Formato, según el instructivo para la aplicación cliente:
 *
 *      TB_ + CuitEmpresa(11) + _ + planta(3) + puerta(3) + _ + aaaammdd + _ + secuencia(6) + .txt
 *
 *      TB_30111111118_000002_20060716_000183.txt
 *
 * Dos cosas que importan:
 *
 * 1. La fecha y el secuencial NO van dentro del archivo: van en el nombre. ARBA
 *    los valida desde ahí y responde los errores 51, 52 y 53 ("Cuit del
 *    archivo", "La fecha del archivo", "El Nro.Secuencial del archivo").
 *
 * 2. El nombre es la clave de idempotencia: el error 11 ("El archivo recibido ya
 *    fue procesado con anterioridad") se dispara por nombre repetido. Reenviar
 *    el mismo nombre es seguro y NO duplica.
 *
 * Nota sobre el instructivo: el ejemplo publicado
 * ("TB_30111111118_003002_20060716_000183.txt") muestra el bloque de 6 dígitos
 * como 003|002, mientras que la propia tabla del documento ejemplifica planta
 * = 000 y puerta = 002. Se implementa lo que dice la definición estructural
 * (planta de 3 + puerta de 3, ambas con ceros a la izquierda) y no el ejemplo,
 * que es contradictorio consigo mismo.
 */
final class CotFileName
{
    public const PREFIX = 'TB';

    public const EXTENSION = 'txt';

    /**
     * Arma el nombre del archivo.
     *
     * @param  \DateTimeInterface|string  $fecha  AAAAMMDD, una fecha ISO o un d/m/Y
     */
    public static function build(string $cuit, string $planta, string $puerta, \DateTimeInterface|string $fecha, string $secuencia): string
    {
        $cuit = preg_replace('/\D/', '', $cuit);

        if (strlen($cuit) !== 11) {
            throw new \InvalidArgumentException('El CUIT de la empresa debe tener 11 dígitos.');
        }

        $planta = self::pad($planta, 3, 'planta');
        $puerta = self::pad($puerta, 3, 'puerta');
        $secuencia = self::pad($secuencia, 6, 'secuencia');

        if ((int) $secuencia === 0) {
            throw new \InvalidArgumentException('El número secuencial debe ser mayor a cero.');
        }

        return implode('_', [
            self::PREFIX,
            $cuit,
            $planta . $puerta,
            self::normalizeFecha($fecha),
            $secuencia,
        ]) . '.' . self::EXTENSION;
    }

    /**
     * Desarma un nombre de archivo.
     *
     * @return array{cuit: string, planta: string, puerta: string, fecha: string, secuencia: string}
     */
    public static function parse(string $nombreArchivo): array
    {
        $pattern = '/^' . self::PREFIX . '_(\d{11})_(\d{3})(\d{3})_(\d{8})_(\d{6})\.' . self::EXTENSION . '$/';

        if (! preg_match($pattern, $nombreArchivo, $matches)) {
            throw new \InvalidArgumentException("El nombre de archivo '{$nombreArchivo}' no respeta el formato TB_[CUIT]__[Planta][Puerta]_[AAAAMMDD]_[Secuencia].txt");
        }

        return [
            'cuit' => $matches[1],
            'planta' => $matches[2],
            'puerta' => $matches[3],
            'fecha' => $matches[4],
            'secuencia' => $matches[5],
        ];
    }

    public static function isValid(string $nombreArchivo): bool
    {
        try {
            self::parse($nombreArchivo);

            return true;
        } catch (\InvalidArgumentException $e) {
            return false;
        }
    }

    /**
     * Fecha en el formato del archivo: AAAAMMDD.
     *
     * Los formatos se prueban explícitamente en lugar de dejar que strtotime
     * adivine. La razón es concreta: '16/07/2006' sacado de los dígitos da
     * '16072006', que como AAAMMDD es el 20 de julio del año 1607. Un archivo
     * con esa fecha se rechaza con el error 52 y el motivo no se ve por ningún
     * lado; peor, una fecha con formato válido pero interpretada al revés
     * (mes y día) pasa sin que nadie la mire.
     */
    public static function normalizeFecha(\DateTimeInterface|string $fecha): string
    {
        if ($fecha instanceof \DateTimeInterface) {
            return $fecha->format('Ymd');
        }

        $fecha = trim($fecha);

        if ($fecha === '') {
            throw new \InvalidArgumentException('La fecha está vacía.');
        }

        $formatos = ['Ymd', 'Y-m-d', 'Y/m/d', 'd/m/Y', 'd-m-Y', 'Y-m-d H:i:s'];

        foreach ($formatos as $formato) {
            $parsed = \DateTimeImmutable::createFromFormat('!' . $formato, $fecha);
            $errores = \DateTimeImmutable::getLastErrors();

            // createFromFormat no falla con un 31/02: devuelve el 3 de marzo y
            // avisa por getLastErrors(). Sin mirar ese aviso, una fecha
            // imposible viaja como si fuera válida.
            $sinAvisos = $errores === false
                || ($errores['warning_count'] === 0 && $errores['error_count'] === 0);

            if ($parsed !== false && $sinAvisos) {
                return $parsed->format('Ymd');
            }
        }

        throw new \InvalidArgumentException("La fecha '{$fecha}' no es válida. Se espera AAAAMMDD.");
    }

    private static function pad(string $value, int $length, string $campo): string
    {
        $value = preg_replace('/\D/', '', $value);

        if ($value === '' || strlen($value) > $length) {
            throw new \InvalidArgumentException("El campo {$campo} debe ser numérico de hasta {$length} dígitos.");
        }

        return str_pad($value, $length, '0', STR_PAD_LEFT);
    }
}
