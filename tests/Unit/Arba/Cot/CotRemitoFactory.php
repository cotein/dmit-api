<?php

namespace Tests\Unit\Arba\Cot;

use App\Src\Arba\Cot\CotAddressData;
use App\Src\Arba\Cot\CotItemData;
use App\Src\Arba\Cot\CotRemitoData;
use App\Src\Arba\Cot\NomencladorEnMemoria;

/**
 * Remito válido de referencia para los tests del COT.
 *
 * Los datos salvo las fechas están tomados del instructivo de ARBA, así que los
 * tests y la documentación hablan del mismo ejemplo. La fecha de salida es del
 * 28/06/2006, por eso los tests de CotValidator tienen que pasar ese mismo día
 * como "hoy": la ventana de la fecha de salida es [hoy - 1, hoy + 30].
 *
 * Los dos CUIT usados pasan la rutina de dígito verificador:
 *   30111111118  (empresa, la del ejemplo del instructivo)
 *   20111111112  (destinatario, el del ejemplo de confirmación de entrega)
 */
final class CotRemitoFactory
{
    public const CUIT_EMPRESA = '30111111118';

    public const CUIT_DESTINATARIO = '20111111112';

    public const HOY_DEL_EJEMPLO = '2006-06-28';

    /**
     * Códigos reales del nomenclador de ARBA, nivel VARIEDAD: son los únicos de
     * seis dígitos y los únicos que pueden ir al registro 03.
     */
    public const PRODUCTO = '110100';

    public const PRODUCTO_ALTERNO = '110200';

    /**
     * Nomenclador en memoria con los códigos que usan los tests, para no
     * depender de la base al probar el generador del TXT.
     */
    public static function nomenclador(array $codigos = []): NomencladorEnMemoria
    {
        return new NomencladorEnMemoria(array_merge([self::PRODUCTO, self::PRODUCTO_ALTERNO], $codigos));
    }

    public static function valid(array $overrides = []): CotRemitoData
    {
        $remito = new CotRemitoData(
            codigoUnico: '91 R000100000001',
            fechaEmision: '2006-06-28',
            fechaSalidaTransporte: '20060628',
            horaSalidaTransporte: '0830',
            sujetoGenerador: CotRemitoData::GENERADOR_EMISOR,
            destinatarioConsumidorFinal: false,
            destinatarioCuit: self::CUIT_DESTINATARIO,
            destinatarioRazonSocial: 'CLIENTE SA',
            destinatarioTenedor: false,
            destino: new CotAddressData(
                calle: 'AV CORRIENTES',
                numero: '1234',
                comple: '',
                piso: '3',
                dto: 'B',
                barrio: 'CENTRO',
                codigoPostal: '1043',
                localidad: 'CABA',
                provincia: 'C',
            ),
            propioDestinoDomicilioCodigo: '',
            entregaDomicilioOrigen: true,
            origenCuit: self::CUIT_EMPRESA,
            origenRazonSocial: 'EMPRESA SA',
            emisorTenedor: true,
            origen: new CotAddressData(
                calle: 'CALLE FALSA',
                numero: '742',
                codigoPostal: '1900',
                localidad: 'LA PLATA',
                provincia: 'B',
            ),
            transportistaCuit: self::CUIT_EMPRESA,
            tipoRecorrido: 'U',
            recorridoLocalidad: 'LA PLATA',
            recorridoRuta: 'RP 11',
            patenteVehiculo: 'AB123CD',
            productoNoTermDev: false,
            importe: 1000,
            items: [self::item()],
        );

        foreach ($overrides as $propiedad => $valor) {
            $remito->{$propiedad} = $valor;
        }

        return $remito;
    }

    public static function item(array $overrides = []): CotItemData
    {
        $item = new CotItemData(
            codigoUnicoProducto: self::PRODUCTO,
            arbaCodigoUnidadMedida: 'K',
            cantidad: 200,
            propioCodigoProducto: 'P001',
            propioDescripcionProducto: 'HARINA 000',
            propioDescripcionUnidadMedida: 'KILO',
            cantidadAjustada: 200,
        );

        foreach ($overrides as $propiedad => $valor) {
            $item->{$propiedad} = $valor;
        }

        return $item;
    }
}
