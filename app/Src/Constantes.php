<?php

namespace App\Src;

class Constantes
{
    const CIENXCIEN = 100;
    /** Afip */
    const MONOTRIBUTO = 6;
    const CUIT_ID = 25;

    /** Afip - Person type */
    const JURIDICA = 1;
    const FISICA = 2;

    const PESOS = 1;

    /** Product Stock */
    const CREA_PRODUCTO = 'CREA PRODUCTO';

    const ERROR_AL_CREAR_COMPAÑIA = 'ERROR AL CREAR COMPAÑIA';
    const ERROR_AL_CREAR_CLIENTE = 'ERROR AL CREAR CLIENTE';
    const ERROR_AL_CREAR_CATEGORIA = 'ERROR AL CREAR CATEGORIA';

    ## FACTURA ELECTRONICA ##
    const ERROR_WSFE_ULTIMO_AUTORIZADO = 'ERROR_WSFE_ULTIMO_AUTORIZADO';
    const ERROR_WSFE_ULTIMO_AUTORIZADO_MENSAJE = 'Ocurrió un error al intentar traer información sobre el último comprobante autorizado';
    const ERROR_WSFE_PTO_VENTA = 'ERROR_WSFE_GET_PTO_VTA';
    const ERROR_WSFE_PTO_VENTA_MENSAJE  = 'Ocurrió un error al intentar traer información sobre el punto de venta de la compañía';
    const FECAESolicitar = 'ERROR_AL_FACTURAR';

    ## ESTADOS ##
    const ADEUDADA = 1;
    const PARCIALMENTE_CANCELADA = 2;
    const CANCELADA = 3;

    ## CONCEPTOS DE FACTURACION ##
    const CONCEPTO_PRODUCTO = 1;
    const CONCEPTO_SERVICIOS = 2;
    const CONCEPTO_PRODUCTOS_Y_SERVICIOS = 3;
}
