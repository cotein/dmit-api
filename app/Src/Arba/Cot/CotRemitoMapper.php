<?php

namespace App\Src\Arba\Cot;

use App\Models\Cot;

/**
 * Pasa un COT guardado a la estructura que consume el generador del archivo.
 *
 * Está acá y no en el modelo ni en el DTO a propósito: el modelo es Eloquent
 * (base de datos, casts) y el DTO es la estructura del registro 02 del archivo.
 * Meterlos juntos ata el formato del TXT a la tabla, y entonces cualquier cambio
 * de esquema toca la generación del archivo.
 */
final class CotRemitoMapper
{
    public function fromModel(Cot $cot): CotRemitoData
    {
        $items = [];

        foreach ($cot->items as $item) {
            $items[] = new CotItemData(
                codigoUnicoProducto: (string) $item->codigo_unico_producto,
                arbaCodigoUnidadMedida: (string) $item->arba_codigo_unidad_medida,
                cantidad: $item->cantidad ?? 0,
                propioCodigoProducto: (string) $item->propio_codigo_producto,
                propioDescripcionProducto: (string) $item->propio_descripcion_producto,
                propioDescripcionUnidadMedida: (string) $item->propio_descripcion_unidad_medida,
                cantidadAjustada: $item->cantidad_ajustada ?? 0,
            );
        }

        return new CotRemitoData(
            codigoUnico: (string) $cot->codigo_unico,
            fechaEmision: $cot->fecha_emision ?? '',
            fechaSalidaTransporte: $cot->fecha_salida_transporte ?? '',
            horaSalidaTransporte: (string) $cot->hora_salida_transporte,
            sujetoGenerador: (string) $cot->sujeto_generador,
            destinatarioConsumidorFinal: (bool) $cot->destinatario_consumidor_final,
            destinatarioTipoDocumento: (string) $cot->destinatario_tipo_documento,
            destinatarioDocumento: (string) $cot->destinatario_documento,
            destinatarioCuit: (string) $cot->destinatario_cuit,
            destinatarioRazonSocial: (string) $cot->destinatario_razon_social,
            destinatarioTenedor: (bool) $cot->destinatario_tenedor,
            destino: new CotAddressData(
                calle: (string) $cot->destino_domicilio_calle,
                numero: (string) $cot->destino_domicilio_numero,
                comple: (string) $cot->destino_domicilio_comple,
                piso: (string) $cot->destino_domicilio_piso,
                dto: (string) $cot->destino_domicilio_dto,
                barrio: (string) $cot->destino_domicilio_barrio,
                codigoPostal: (string) $cot->destino_domicilio_codigo_postal,
                localidad: (string) $cot->destino_domicilio_localidad,
                provincia: (string) $cot->destino_domicilio_provincia,
            ),
            propioDestinoDomicilioCodigo: (string) $cot->propio_destino_domicilio_codigo,
            entregaDomicilioOrigen: (bool) $cot->entrega_domicilio_origen,
            origenCuit: (string) $cot->origen_cuit,
            origenRazonSocial: (string) $cot->origen_razon_social,
            emisorTenedor: (bool) $cot->emisor_tenedor,
            origen: new CotAddressData(
                calle: (string) $cot->origen_domicilio_calle,
                numero: (string) $cot->origen_domicilio_numero,
                comple: (string) $cot->origen_domicilio_comple,
                piso: (string) $cot->origen_domicilio_piso,
                dto: (string) $cot->origen_domicilio_dto,
                barrio: (string) $cot->origen_domicilio_barrio,
                codigoPostal: (string) $cot->origen_domicilio_codigo_postal,
                localidad: (string) $cot->origen_domicilio_localidad,
                provincia: (string) $cot->origen_domicilio_provincia,
            ),
            transportistaCuit: (string) $cot->transportista_cuit,
            tipoRecorrido: (string) $cot->tipo_recorrido,
            recorridoLocalidad: (string) $cot->recorrido_localidad,
            recorridoCalle: (string) $cot->recorrido_calle,
            recorridoRuta: (string) $cot->recorrido_ruta,
            patenteVehiculo: (string) $cot->patente_vehiculo,
            patenteAcoplado: (string) $cot->patente_acoplado,
            productoNoTermDev: (bool) $cot->producto_no_term_dev,
            importe: $cot->importe ?? 0,
            items: $items,
        );
    }

    /**
     * @param  iterable<Cot>  $cots
     * @return array<int, CotRemitoData>
     */
    public function fromCollection(iterable $cots): array
    {
        $remitos = [];

        foreach ($cots as $cot) {
            $remitos[] = $this->fromModel($cot);
        }

        return $remitos;
    }
}
