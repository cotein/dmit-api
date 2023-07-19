<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AfipVouchersTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {


        DB::table('vouchers')->delete();

        DB::table('vouchers')->insert(array(
            0 =>
            array(
                'id' => 1,
                'afip_code' => '001',
                'name' => 'FACTURAS A',
                'inscription_id' => 1,
            ),
            1 =>
            array(
                'id' => 2,
                'afip_code' => '002',
                'name' => 'NOTAS DE DEBITO A',
                'inscription_id' => 1,
            ),
            2 =>
            array(
                'id' => 3,
                'afip_code' => '003',
                'name' => 'NOTAS DE CREDITO A',
                'inscription_id' => 1,
            ),
            3 =>
            array(
                'id' => 4,
                'afip_code' => '004',
                'name' => 'RECIBOS A',
                'inscription_id' => 1,
            ),
            4 =>
            array(
                'id' => 5,
                'afip_code' => '005',
                'name' => 'NOTAS DE VENTA AL CONTADO A',
                'inscription_id' => 1,
            ),
            5 =>
            array(
                'id' => 6,
                'afip_code' => '006',
                'name' => 'FACTURAS B',
                'inscription_id' => NULL,
            ),
            6 =>
            array(
                'id' => 7,
                'afip_code' => '007',
                'name' => 'NOTAS DE DEBITO B',
                'inscription_id' => NULL,
            ),
            7 =>
            array(
                'id' => 8,
                'afip_code' => '008',
                'name' => 'NOTAS DE CREDITO B',
                'inscription_id' => NULL,
            ),
            8 =>
            array(
                'id' => 9,
                'afip_code' => '009',
                'name' => 'RECIBOS B',
                'inscription_id' => NULL,
            ),
            9 =>
            array(
                'id' => 10,
                'afip_code' => '010',
                'name' => 'NOTAS DE VENTA AL CONTADO B',
                'inscription_id' => NULL,
            ),
            10 =>
            array(
                'id' => 11,
                'afip_code' => '011',
                'name' => 'FACTURAS C',
                'inscription_id' => 6,
            ),
            11 =>
            array(
                'id' => 12,
                'afip_code' => '012',
                'name' => 'NOTAS DE DEBITO C',
                'inscription_id' => 6,
            ),
            12 =>
            array(
                'id' => 13,
                'afip_code' => '013',
                'name' => 'NOTAS DE CREDITO C',
                'inscription_id' => 6,
            ),
            13 =>
            array(
                'id' => 14,
                'afip_code' => '015',
                'name' => 'RECIBOS C',
                'inscription_id' => 6,
            ),
            14 =>
            array(
                'id' => 15,
                'afip_code' => '016',
                'name' => 'NOTAS DE VENTA AL CONTADO C',
                'inscription_id' => 6,
            ),
            15 =>
            array(
                'id' => 16,
                'afip_code' => '017',
                'name' => 'LIQUIDACION DE SERVICIOS PUBLICOS CLASE A',
                'inscription_id' => NULL,
            ),
            16 =>
            array(
                'id' => 17,
                'afip_code' => '018',
                'name' => 'LIQUIDACION DE SERVICIOS PUBLICOS CLASE B',
                'inscription_id' => NULL,
            ),
            17 =>
            array(
                'id' => 18,
                'afip_code' => '019',
                'name' => 'FACTURAS DE EXPORTACION',
                'inscription_id' => NULL,
            ),
            18 =>
            array(
                'id' => 19,
                'afip_code' => '020',
                'name' => 'NOTAS DE DEBITO POR OPERACIONES CON EL EXTERIOR',
                'inscription_id' => NULL,
            ),
            19 =>
            array(
                'id' => 20,
                'afip_code' => '021',
                'name' => 'NOTAS DE CREDITO POR OPERACIONES CON EL EXTERIOR',
                'inscription_id' => NULL,
            ),
            20 =>
            array(
                'id' => 21,
                'afip_code' => '022',
                'name' => 'FACTURAS - PERMISO EXPORTACION SIMPLIFICADO - DTO. 855/97',
                'inscription_id' => NULL,
            ),
            21 =>
            array(
                'id' => 22,
                'afip_code' => '023',
                'name' => 'COMPROBANTES â€œAâ€ DE COMPRA PRIMARIA PARA EL SECTOR PESQUERO MARITIMO',
                'inscription_id' => NULL,
            ),
            22 =>
            array(
                'id' => 23,
                'afip_code' => '024',
                'name' => 'COMPROBANTES â€œAâ€ DE CONSIGNACION PRIMARIA PARA EL SECTOR PESQUERO MARITIMO',
                'inscription_id' => NULL,
            ),
            23 =>
            array(
                'id' => 24,
                'afip_code' => '025',
                'name' => 'COMPROBANTES â€œBâ€ DE COMPRA PRIMARIA PARA EL SECTOR PESQUERO MARITIMO',
                'inscription_id' => NULL,
            ),
            24 =>
            array(
                'id' => 25,
                'afip_code' => '026',
                'name' => 'COMPROBANTES â€œBâ€ DE CONSIGNACION PRIMARIA PARA EL SECTOR PESQUERO MARITIMO',
                'inscription_id' => NULL,
            ),
            25 =>
            array(
                'id' => 26,
                'afip_code' => '027',
                'name' => 'LIQUIDACION UNICA COMERCIAL IMPOSITIVA CLASE A',
                'inscription_id' => NULL,
            ),
            26 =>
            array(
                'id' => 27,
                'afip_code' => '028',
                'name' => 'LIQUIDACION UNICA COMERCIAL IMPOSITIVA CLASE B',
                'inscription_id' => NULL,
            ),
            27 =>
            array(
                'id' => 28,
                'afip_code' => '029',
                'name' => 'LIQUIDACION UNICA COMERCIAL IMPOSITIVA CLASE C',
                'inscription_id' => NULL,
            ),
            28 =>
            array(
                'id' => 29,
                'afip_code' => '030',
                'name' => 'COMPROBANTES DE COMPRA DE BIENES USADOS',
                'inscription_id' => NULL,
            ),
            29 =>
            array(
                'id' => 30,
                'afip_code' => '031',
                'name' => 'MANDATO - CONSIGNACION',
                'inscription_id' => NULL,
            ),
            30 =>
            array(
                'id' => 31,
                'afip_code' => '032',
                'name' => 'COMPROBANTES PARA RECICLAR MATERIALES',
                'inscription_id' => NULL,
            ),
            31 =>
            array(
                'id' => 32,
                'afip_code' => '033',
                'name' => 'LIQUIDACION PRIMARIA DE GRANOS',
                'inscription_id' => NULL,
            ),
            32 =>
            array(
                'id' => 33,
                'afip_code' => '034',
                'name' => 'COMPROBANTES A DEL APARTADO A  INCISO F)  R.G. NÂ°  1415',
                'inscription_id' => NULL,
            ),
            33 =>
            array(
                'id' => 34,
                'afip_code' => '035',
                'name' => 'COMPROBANTES B DEL ANEXO I, APARTADO A, INC. F], R.G. NÂ° 1415',
                'inscription_id' => NULL,
            ),
            34 =>
            array(
                'id' => 35,
                'afip_code' => '036',
                'name' => 'COMPROBANTES C DEL Anexo I, Apartado A, INC.F], R.G. NÂ° 1415',
                'inscription_id' => NULL,
            ),
            35 =>
            array(
                'id' => 36,
                'afip_code' => '037',
                'name' => 'NOTAS DE DEBITO O DOCUMENTO EQUIVALENTE QUE CUMPLAN CON LA R.G. NÂ° 1415',
                'inscription_id' => NULL,
            ),
            36 =>
            array(
                'id' => 37,
                'afip_code' => '038',
                'name' => 'NOTAS DE CREDITO O DOCUMENTO EQUIVALENTE QUE CUMPLAN CON LA R.G. NÂ° 1415',
                'inscription_id' => NULL,
            ),
            37 =>
            array(
                'id' => 38,
                'afip_code' => '039',
                'name' => 'OTROS COMPROBANTES A QUE CUMPLEN CON LA R G  1415',
                'inscription_id' => NULL,
            ),
            38 =>
            array(
                'id' => 39,
                'afip_code' => '040',
                'name' => 'OTROS COMPROBANTES B QUE CUMPLAN CON LA R.G. NÂ° 1415',
                'inscription_id' => NULL,
            ),
            39 =>
            array(
                'id' => 40,
                'afip_code' => '041',
                'name' => 'OTROS COMPROBANTES C QUE CUMPLAN CON LA R.G. NÂ° 1415',
                'inscription_id' => NULL,
            ),
            40 =>
            array(
                'id' => 41,
                'afip_code' => '043',
                'name' => 'NOTA DE CREDITO LIQUIDACION UNICA COMERCIAL IMPOSITIVA CLASE B',
                'inscription_id' => NULL,
            ),
            41 =>
            array(
                'id' => 42,
                'afip_code' => '044',
                'name' => 'NOTA DE CREDITO LIQUIDACION UNICA COMERCIAL IMPOSITIVA CLASE C',
                'inscription_id' => NULL,
            ),
            42 =>
            array(
                'id' => 43,
                'afip_code' => '045',
                'name' => 'NOTA DE DEBITO LIQUIDACION UNICA COMERCIAL IMPOSITIVA CLASE A',
                'inscription_id' => NULL,
            ),
            43 =>
            array(
                'id' => 44,
                'afip_code' => '046',
                'name' => 'NOTA DE DEBITO LIQUIDACION UNICA COMERCIAL IMPOSITIVA CLASE B',
                'inscription_id' => NULL,
            ),
            44 =>
            array(
                'id' => 45,
                'afip_code' => '047',
                'name' => 'NOTA DE DEBITO LIQUIDACION UNICA COMERCIAL IMPOSITIVA CLASE C',
                'inscription_id' => NULL,
            ),
            45 =>
            array(
                'id' => 46,
                'afip_code' => '048',
                'name' => 'NOTA DE CREDITO LIQUIDACION UNICA COMERCIAL IMPOSITIVA CLASE A',
                'inscription_id' => NULL,
            ),
            46 =>
            array(
                'id' => 47,
                'afip_code' => '049',
                'name' => 'COMPROBANTES DE COMPRA DE BIENES NO REGISTRABLES A CONSUMIDORES FINALES',
                'inscription_id' => NULL,
            ),
            47 =>
            array(
                'id' => 48,
                'afip_code' => '050',
                'name' => 'RECIBO FACTURA A  REGIMEN DE FACTURA DE CREDITO ',
                'inscription_id' => NULL,
            ),
            48 =>
            array(
                'id' => 49,
                'afip_code' => '051',
                'name' => 'FACTURAS M',
                'inscription_id' => 1,
            ),
            49 =>
            array(
                'id' => 50,
                'afip_code' => '052',
                'name' => 'NOTAS DE DEBITO M',
                'inscription_id' => 1,
            ),
            50 =>
            array(
                'id' => 51,
                'afip_code' => '053',
                'name' => 'NOTAS DE CREDITO M',
                'inscription_id' => 1,
            ),
            51 =>
            array(
                'id' => 52,
                'afip_code' => '054',
                'name' => 'RECIBOS M',
                'inscription_id' => 1,
            ),
            52 =>
            array(
                'id' => 53,
                'afip_code' => '055',
                'name' => 'NOTAS DE VENTA AL CONTADO M',
                'inscription_id' => 1,
            ),
            53 =>
            array(
                'id' => 54,
                'afip_code' => '056',
                'name' => 'COMPROBANTES M DEL ANEXO I  APARTADO A  INC F) R.G. NÂ° 1415',
                'inscription_id' => NULL,
            ),
            54 =>
            array(
                'id' => 55,
                'afip_code' => '057',
                'name' => 'OTROS COMPROBANTES M QUE CUMPLAN CON LA R.G. NÂ° 1415',
                'inscription_id' => NULL,
            ),
            55 =>
            array(
                'id' => 56,
                'afip_code' => '058',
                'name' => 'CUENTAS DE VENTA Y LIQUIDO PRODUCTO M',
                'inscription_id' => NULL,
            ),
            56 =>
            array(
                'id' => 57,
                'afip_code' => '059',
                'name' => 'LIQUIDACIONES M',
                'inscription_id' => NULL,
            ),
            57 =>
            array(
                'id' => 58,
                'afip_code' => '060',
                'name' => 'CUENTAS DE VENTA Y LIQUIDO PRODUCTO A',
                'inscription_id' => NULL,
            ),
            58 =>
            array(
                'id' => 59,
                'afip_code' => '061',
                'name' => 'CUENTAS DE VENTA Y LIQUIDO PRODUCTO B',
                'inscription_id' => NULL,
            ),
            59 =>
            array(
                'id' => 60,
                'afip_code' => '063',
                'name' => 'LIQUIDACIONES A',
                'inscription_id' => NULL,
            ),
            60 =>
            array(
                'id' => 61,
                'afip_code' => '064',
                'name' => 'LIQUIDACIONES B',
                'inscription_id' => NULL,
            ),
            61 =>
            array(
                'id' => 62,
                'afip_code' => '066',
                'name' => 'DESPACHO DE IMPORTACION',
                'inscription_id' => NULL,
            ),
            62 =>
            array(
                'id' => 63,
                'afip_code' => '068',
                'name' => 'LIQUIDACION C',
                'inscription_id' => NULL,
            ),
            63 =>
            array(
                'id' => 64,
                'afip_code' => '070',
                'name' => 'RECIBOS FACTURA DE CREDITO',
                'inscription_id' => NULL,
            ),
            64 =>
            array(
                'id' => 65,
                'afip_code' => '080',
                'name' => 'INFORME DIARIO DE CIERRE (ZETA) - CONTROLADORES FISCALES',
                'inscription_id' => NULL,
            ),
            65 =>
            array(
                'id' => 66,
                'afip_code' => '081',
                'name' => 'TIQUE FACTURA A   ',
                'inscription_id' => 1,
            ),
            66 =>
            array(
                'id' => 67,
                'afip_code' => '082',
                'name' => 'TIQUE FACTURA B',
                'inscription_id' => NULL,
            ),
            67 =>
            array(
                'id' => 68,
                'afip_code' => '083',
                'name' => 'TIQUE',
                'inscription_id' => NULL,
            ),
            68 =>
            array(
                'id' => 69,
                'afip_code' => '088',
                'name' => 'REMITO ELECTRONICO',
                'inscription_id' => NULL,
            ),
            69 =>
            array(
                'id' => 70,
                'afip_code' => '089',
                'name' => 'RESUMEN DE DATOS',
                'inscription_id' => NULL,
            ),
            70 =>
            array(
                'id' => 71,
                'afip_code' => '090',
                'name' => 'OTROS COMPROBANTES - DOCUMENTOS EXCEPTUADOS - NOTAS DE CREDITO',
                'inscription_id' => NULL,
            ),
            71 =>
            array(
                'id' => 72,
                'afip_code' => '091',
                'name' => 'REMITOS R',
                'inscription_id' => NULL,
            ),
            72 =>
            array(
                'id' => 73,
                'afip_code' => '099',
                'name' => 'OTROS COMPROBANTES QUE NO CUMPLEN O ESTÃN EXCEPTUADOS DE LA R.G. 1415 Y SUS MODIF ',
                'inscription_id' => NULL,
            ),
            73 =>
            array(
                'id' => 74,
                'afip_code' => '110',
                'name' => 'TIQUE NOTA DE CREDITO ',
                'inscription_id' => NULL,
            ),
            74 =>
            array(
                'id' => 75,
                'afip_code' => '111',
                'name' => 'TIQUE FACTURA C',
                'inscription_id' => 6,
            ),
            75 =>
            array(
                'id' => 76,
                'afip_code' => '112',
                'name' => 'TIQUE NOTA DE CREDITO A',
                'inscription_id' => 1,
            ),
            76 =>
            array(
                'id' => 77,
                'afip_code' => '113',
                'name' => 'TIQUE NOTA DE CREDITO B',
                'inscription_id' => NULL,
            ),
            77 =>
            array(
                'id' => 78,
                'afip_code' => '114',
                'name' => 'TIQUE NOTA DE CREDITO C',
                'inscription_id' => 6,
            ),
            78 =>
            array(
                'id' => 79,
                'afip_code' => '115',
                'name' => 'TIQUE NOTA DE DEBITO A',
                'inscription_id' => 1,
            ),
            79 =>
            array(
                'id' => 80,
                'afip_code' => '116',
                'name' => 'TIQUE NOTA DE DEBITO B',
                'inscription_id' => NULL,
            ),
            80 =>
            array(
                'id' => 81,
                'afip_code' => '117',
                'name' => 'TIQUE NOTA DE DEBITO C',
                'inscription_id' => 6,
            ),
            81 =>
            array(
                'id' => 82,
                'afip_code' => '118',
                'name' => 'TIQUE FACTURA M',
                'inscription_id' => 1,
            ),
            82 =>
            array(
                'id' => 83,
                'afip_code' => '119',
                'name' => 'TIQUE NOTA DE CREDITO M',
                'inscription_id' => 1,
            ),
            83 =>
            array(
                'id' => 84,
                'afip_code' => '120',
                'name' => 'TIQUE NOTA DE DEBITO M',
                'inscription_id' => 1,
            ),
            84 =>
            array(
                'id' => 85,
                'afip_code' => '331',
                'name' => 'LIQUIDACION SECUNDARIA DE GRANOS',
                'inscription_id' => NULL,
            ),
            85 =>
            array(
                'id' => 86,
                'afip_code' => '332',
                'name' => 'CERTIFICACION ELECTRONICA (GRANOS)',
                'inscription_id' => NULL,
            ),
            86 =>
            array(
                'id' => 87,
                'afip_code' => '998',
                'name' => 'RECIBO',
                'inscription_id' => NULL,
            ),
            87 =>
            array(
                'id' => 88,
                'afip_code' => '999',
                'name' => 'PRESUPUESTO VENTA',
                'inscription_id' => NULL,
            ),
            88 =>
            array(
                'id' => 89,
                'afip_code' => '951',
                'name' => 'PAGO A CUENTA',
                'inscription_id' => NULL,
            ),
            89 =>
            array(
                'id' => 90,
                'afip_code' => '950',
                'name' => 'ORDEN DE PAGO',
                'inscription_id' => NULL,
            ),
            90 =>
            array(
                'id' => 91,
                'afip_code' => '952',
                'name' => 'ORDEN DE COMPRA',
                'inscription_id' => NULL,
            ),
            91 =>
            array(
                'id' => 92,
                'afip_code' => '201',
                'name' => 'FACTURA DE CRÃ‰DITO ELECTRÃ“NICA MiPyME (FCE) A',
                'inscription_id' => NULL,
            ),
            92 =>
            array(
                'id' => 93,
                'afip_code' => '202',
                'name' => 'NOTA DE DÃ‰BITO ELECTRÃ“NICA MiPyME (FCE) A',
                'inscription_id' => NULL,
            ),
            93 =>
            array(
                'id' => 94,
                'afip_code' => '203',
                'name' => 'NOTA DE CRÃ‰DITO ELECTRÃ“NICA MiPyME (FCE) A',
                'inscription_id' => NULL,
            ),
            94 =>
            array(
                'id' => 95,
                'afip_code' => '206',
                'name' => 'FACTURA DE CRÃ‰DITO ELECTRÃ“NICA MiPyME (FCE) B',
                'inscription_id' => NULL,
            ),
            95 =>
            array(
                'id' => 96,
                'afip_code' => '207',
                'name' => 'NOTA DE DÃ‰BITO ELECTRÃ“NICA MiPyME (FCE) B',
                'inscription_id' => NULL,
            ),
            96 =>
            array(
                'id' => 97,
                'afip_code' => '208',
                'name' => 'NOTA DE CRÃ‰DITO ELECTRÃ“NICA MiPyME (FCE) B',
                'inscription_id' => NULL,
            ),
            97 =>
            array(
                'id' => 98,
                'afip_code' => '211',
                'name' => 'FACTURA DE CRÃ‰DITO ELECTRÃ“NICA MiPyME (FCE) C',
                'inscription_id' => NULL,
            ),
            98 =>
            array(
                'id' => 99,
                'afip_code' => '212',
                'name' => 'NOTA DE DÃ‰BITO ELECTRÃ“NICA MiPyME (FCE) C',
                'inscription_id' => NULL,
            ),
            99 =>
            array(
                'id' => 100,
                'afip_code' => '213',
                'name' => 'NOTA DE CRÃ‰DITO ELECTRÃ“NICA MiPyME (FCE) C',
                'inscription_id' => NULL,
            ),
            100 =>
            array(
                'id' => 101,
                'afip_code' => '1000',
                'name' => 'PEDIDO CLIENTE',
                'inscription_id' => NULL,
            ),
            101 =>
            array(
                'id' => 102,
                'afip_code' => '1001',
                'name' => 'COTIZACION',
                'inscription_id' => NULL,
            ),
        ));
    }
}
