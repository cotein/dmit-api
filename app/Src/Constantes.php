<?php

namespace App\Src;

use Cotein\ApiAfip\Afip\WS\WSFECRED;

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

    const RESPONSABLE_MONOTRIBUTO = 'Responsable Monotributo';
    const IVA_SUJETO_EXENTO = 'IVA Sujeto Exento';
    const IVA_RESPONSABLE_INSCRIPTO = 'IVA Responsable Inscripto';

    ## CONDICION DE VENTA ##
    const CONTADO = 1;

    const IS_FACTURA_AFIP_CODE = [1, 6, 11, 201, 206, 211]; //son los id, pueden coincidir con los códigos de afip
    const IS_NOTA_CREDITO_AFIP_CODE = [3, 8, 13, 203, 208, 213]; //son los id, pueden coincidir con los códigos de afip

    const IS_NOTA_CREDITO = [3, 8, 13, 20, 51, 94, 97, 100]; //son los id, pueden coincidir con los códigos de afip

    ## INSCRIPCION EN AFIP ##
    const INSCRIPCION_RESPONSABLE_INSCRIPTO = 1;
    const INSCRIPCION_CONSUMIDOR_FINAL = 5;
    const INSCRIPCION_RESPONSABLE_MONOTRIBUTO = 6;
    const INSCRIPCION_IVA_EXENTO = 4;

    //usuarios
    const USER_ROOT = 1;
    const USER_ADMIN = 2;
    const USER_USER = 3;

    const PRODUCTION_ENVIRONMENT = 'PRODUCTION';
    const TESTING_ENVIRONMENT = 'TESTING';

    const DIEGO_BARRUETA_CUIT = 20227339730;

    const WSFECRED = [
        'FCA' => 201,
        'NDA' => 202,
        'NCA' => 203,
        'FCB' => 206,
        'NDB' => 207,
        'NCB' => 208,
        'FCC' => 211,
        'NDC' => 212,
        'NCC' => 213,
    ];
}
