<?php

namespace App\Src\InvoicesSale;

class INVOICES_CONST
{
    /** STATUSES */
    const ACTIVO = 1;
    const PENDIENTE = 2;
    const REMITIDO = 3;
    const PRESUPUESTADO = 4;
    const FACTURADO = 5;
    const PREPARADO = 6;
    const RETIRADO = 7;
    const DESPACHADO = 8;
    const ENTREGADO = 9;
    const ELIMINADO = 10;
    const CUIT_LENGHT = 11;

    /** INVOICES TYPES */
    const FACTURA = 1;
    const NOTA_DE_DEBITO = 2;
    const NOTA_DE_CREDITO = 3;

    const FACTURA_A = 1;
    const FACTURA_B = 6;

    const NOTA_DEBITO_A = 2;
    const NOTA_DEBITO_B = 7;

    const NOTA_CREDITO = 3;
    const NOTA_CREDITO_A = 3;
    const NOTA_CREDITO_B = 8;

    /** INSCRIPTION TYPES */
    const IVA_RESPONSABLE_INSCRIPTO = 1;
    const IVA_RESPONSABLE_NO_INSCRIPTO = 2;
    const IVA_NO_RESPONSABLE = 3;
    const IVA_SUJETO_EXENTO = 4;
    const CONSUMIDOR_FINAL = 5;
    const RESPONSABLE_MONOTRIBUTO = 6;
    const SUJETO_NO_CATEGORIZADO = 7;
    const PROVEEDOR_DEL_EXTERIOR = 8;
    const CLIENTE_DEL_EXTERIOR = 9;
    const IVA_LIBERADO = 10;
    const IVA_RESPONSABLE_INSCRIPTO_AGENTE_DE_PERCEPCION = 11;
    const PEQUEÑO_CONTRIBUYENTE = 12;
    const MONOTRIBUTISTA_SOCIAL = 13;
    const PEQUEÑO_CONTRIBUYENTE_SOCIAL = 14;

    const PATH_TO_INVOICES_CREATORS = 'App\\Src\\InvoicesSale\\InvoicesCreators';

    const INVOICES_CREATORS = [
        /** COMPANY RESPONSABLE INSCRIPTO */
        [
            'invoice_type' => self::FACTURA,
            'inscription_company' => self::IVA_RESPONSABLE_INSCRIPTO,
            'inscription_customer' => self::IVA_RESPONSABLE_INSCRIPTO,
            'class' => 'FacturaACreator'
        ],
        [
            'invoice_type' => self::FACTURA,
            'inscription_company' => self::IVA_RESPONSABLE_INSCRIPTO,
            'inscription_customer' => self::CONSUMIDOR_FINAL,
            'class' => 'FacturaBCreator'
        ],
        [
            'invoice_type' => self::FACTURA,
            'inscription_company' => self::IVA_RESPONSABLE_INSCRIPTO,
            'inscription_customer' => self::RESPONSABLE_MONOTRIBUTO,
            'class' => 'FacturaACreator'
        ],
        [
            'invoice_type' => self::FACTURA,
            'inscription_company' => self::IVA_RESPONSABLE_INSCRIPTO,
            'inscription_customer' => self::IVA_SUJETO_EXENTO,
            'class' => 'FacturaBCreator'
        ],
        [
            'invoice_type' => self::NOTA_DE_DEBITO,
            'inscription_company' => self::IVA_RESPONSABLE_INSCRIPTO,
            'inscription_customer' => self::IVA_RESPONSABLE_INSCRIPTO,
            'class' => 'NotaDebitoACreator'
        ],
        [
            'invoice_type' => self::NOTA_DE_DEBITO,
            'inscription_company' => self::IVA_RESPONSABLE_INSCRIPTO,
            'inscription_customer' => self::CONSUMIDOR_FINAL,
            'class' => 'NotaDebitoBCreator'
        ],
        [
            'invoice_type' => self::NOTA_DE_DEBITO,
            'inscription_company' => self::IVA_RESPONSABLE_INSCRIPTO,
            'inscription_customer' => self::RESPONSABLE_MONOTRIBUTO,
            'class' => 'NotaDebitoACreator'
        ],
        [
            'invoice_type' => self::NOTA_DE_DEBITO,
            'inscription_company' => self::IVA_RESPONSABLE_INSCRIPTO,
            'inscription_customer' => self::IVA_SUJETO_EXENTO,
            'class' => 'NotaDebitoBCreator'
        ],
        [
            'invoice_type' => self::NOTA_DE_CREDITO,
            'inscription_company' => self::IVA_RESPONSABLE_INSCRIPTO,
            'inscription_customer' => self::IVA_RESPONSABLE_INSCRIPTO,
            'class' => 'NotaCreditoACreator'
        ],
        [
            'invoice_type' => self::NOTA_DE_CREDITO,
            'inscription_company' => self::IVA_RESPONSABLE_INSCRIPTO,
            'inscription_customer' => self::CONSUMIDOR_FINAL,
            'class' => 'NotaCreditoBCreator'
        ],
        [
            'invoice_type' => self::NOTA_DE_CREDITO,
            'inscription_company' => self::IVA_RESPONSABLE_INSCRIPTO,
            'inscription_customer' => self::RESPONSABLE_MONOTRIBUTO,
            'class' => 'NotaCreditoACreator'
        ],
        [
            'invoice_type' => self::NOTA_DE_CREDITO,
            'inscription_company' => self::IVA_RESPONSABLE_INSCRIPTO,
            'inscription_customer' => self::IVA_SUJETO_EXENTO,
            'class' => 'NotaCreditoBCreator'
        ],

        /** COMPANY MONOTRIBUTO */
        [
            'invoice_type' => self::FACTURA,
            'inscription_company' => self::RESPONSABLE_MONOTRIBUTO,
            'inscription_customer' => self::IVA_RESPONSABLE_INSCRIPTO,
            'class' => 'FacturaBCreator'
        ],
        [
            'invoice_type' => self::FACTURA,
            'inscription_company' => self::RESPONSABLE_MONOTRIBUTO,
            'inscription_customer' => self::CONSUMIDOR_FINAL,
            'class' => 'FacturaBCreator'
        ],
        [
            'invoice_type' => self::FACTURA,
            'inscription_company' => self::RESPONSABLE_MONOTRIBUTO,
            'inscription_customer' => self::RESPONSABLE_MONOTRIBUTO,
            'class' => 'FacturaBCreator'
        ],
        [
            'invoice_type' => self::NOTA_DE_DEBITO,
            'inscription_company' => self::RESPONSABLE_MONOTRIBUTO,
            'inscription_customer' => self::IVA_RESPONSABLE_INSCRIPTO,
            'class' => 'NotaDebitoBCreator'
        ],
        [
            'invoice_type' => self::NOTA_DE_DEBITO,
            'inscription_company' => self::RESPONSABLE_MONOTRIBUTO,
            'inscription_customer' => self::CONSUMIDOR_FINAL,
            'class' => 'NotaDebitoBCreator'
        ],
        [
            'invoice_type' => self::NOTA_DE_DEBITO,
            'inscription_company' => self::RESPONSABLE_MONOTRIBUTO,
            'inscription_customer' => self::RESPONSABLE_MONOTRIBUTO,
            'class' => 'NotaDebitoBCreator'
        ],
        [
            'invoice_type' => self::NOTA_DE_CREDITO,
            'inscription_company' => self::RESPONSABLE_MONOTRIBUTO,
            'inscription_customer' => self::IVA_RESPONSABLE_INSCRIPTO,
            'class' => 'NotaCreditoBCreator'
        ],
        [
            'invoice_type' => self::NOTA_DE_CREDITO,
            'inscription_company' => self::RESPONSABLE_MONOTRIBUTO,
            'inscription_customer' => self::CONSUMIDOR_FINAL,
            'class' => 'NotaCreditoBCreator'
        ],
        [
            'invoice_type' => self::NOTA_DE_CREDITO,
            'inscription_company' => self::RESPONSABLE_MONOTRIBUTO,
            'inscription_customer' => self::RESPONSABLE_MONOTRIBUTO,
            'class' => 'NotaCreditoBCreator'
        ],
    ];

    /* #### WSAA ####
    const WSAA_TESTING_LOGINCMS = 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms';
    const WSAA_PRODUCTION_LOGINCMS = 'https://wsaa.afip.gov.ar/ws/services/LoginCms';

    #### WSPUC5 ####
    const WSPUC5_TESTING = "https://awshomo.afip.gov.ar/sr-padron/webservices/personaServiceA5?WSDL";
    const WSPUC5_PRODUCTION = "https://aws.afip.gov.ar/sr-padron/webservices/personaServiceA5?WSDL";

    #### WSPUC13 ####
    const WSPUC13_TESTING = "https://awshomo.afip.gov.ar/sr-padron/webservices/personaServiceA13?WSDL";
    const WSPUC13_PRODUCTION = "https://aws.afip.gov.ar/sr-padron/webservices/personaServiceA13?WSDL";

    #### WSFECRED ####
    const WSFECRED_TESTING = 'https://fwshomo.afip.gov.ar/wsfecred/FECredService?wsdl';
    const WSFECRED_PRODUCTION = 'https://serviciosjava.afip.gob.ar/wsfecred/FECredService?wsdl'; */



    /* public static function getWSDL($wsdl)
    {
        $class = new \ReflectionClass(__CLASS__);

        $constants = $class->getConstants();

        return $constants[$wsdl];
    } */
}
