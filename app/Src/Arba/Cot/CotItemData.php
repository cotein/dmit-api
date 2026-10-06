<?php

namespace App\Src\Arba\Cot;

/**
 * Una línea de mercadería: el registro 03 del archivo TXT.
 *
 * cantidad y cantidadAjustada se normalizan con dos decimales implícitos al
 * armar el archivo (ver CotNumber).
 */
final class CotItemData
{
    public function __construct(
        public string $codigoUnicoProducto = '',
        public string $arbaCodigoUnidadMedida = '',
        public float|string $cantidad = 0,
        public string $propioCodigoProducto = '',
        public string $propioDescripcionProducto = '',
        public string $propioDescripcionUnidadMedida = '',
        public float|string $cantidadAjustada = 0,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            codigoUnicoProducto: self::normalizeCodigo($data['codigo_unico_producto'] ?? ''),
            arbaCodigoUnidadMedida: strtoupper(trim((string) ($data['arba_codigo_unidad_medida'] ?? ''))),
            cantidad: $data['cantidad'] ?? 0,
            propioCodigoProducto: trim((string) ($data['propio_codigo_producto'] ?? '')),
            propioDescripcionProducto: trim((string) ($data['propio_descripcion_producto'] ?? '')),
            propioDescripcionUnidadMedida: trim((string) ($data['propio_descripcion_unidad_medida'] ?? '')),
            cantidadAjustada: $data['cantidad_ajustada'] ?? 0,
        );
    }

    /**
     * El nomenclador de ARBA son 6 dígitos; se completa con ceros a la izquierda
     * para que un "1" cargado a mano no viaje como un campo de largo distinto.
     */
    private static function normalizeCodigo($value): string
    {
        $value = preg_replace('/\D/', '', (string) $value);

        if ($value === '' || $value === null) {
            return '';
        }

        return str_pad($value, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Los 7 valores del registro 03, sin el tipo de registro.
     *
     * @return string[]
     */
    public function toRegistro(): array
    {
        return [
            $this->codigoUnicoProducto,
            $this->arbaCodigoUnidadMedida,
            CotNumber::decimal($this->cantidad),
            $this->propioCodigoProducto,
            $this->propioDescripcionProducto,
            $this->propioDescripcionUnidadMedida,
            CotNumber::decimal($this->cantidadAjustada),
        ];
    }
}
