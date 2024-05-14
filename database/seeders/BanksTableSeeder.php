<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BanksTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {


        DB::table('banks')->truncate();

        DB::table('banks')->insert(array(
            0 =>
            array(
                'id' => 1,
                'code' => 7,
                'name' => 'BANCO DE GALICIA Y BUENOS AIRES S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            1 =>
            array(
                'id' => 2,
                'code' => 11,
                'name' => 'BANCO DE LA NACION ARGENTINA',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            2 =>
            array(
                'id' => 3,
                'code' => 14,
                'name' => 'BANCO DE LA PROVINCIA DE BUENOS AIRES',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            3 =>
            array(
                'id' => 4,
                'code' => 15,
                'name' => 'INDUSTRIAL AND COMMERCIAL BANK OF CHINA',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            4 =>
            array(
                'id' => 5,
                'code' => 16,
                'name' => 'CITIBANK N.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            5 =>
            array(
                'id' => 6,
                'code' => 17,
                'name' => 'BBVA BANCO FRANCES S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            6 =>
            array(
                'id' => 7,
                'code' => 18,
                'name' => 'THE BANK OF TOKYO-MITSUBISHI UFJ, LTD.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            7 =>
            array(
                'id' => 8,
                'code' => 20,
                'name' => 'BANCO DE LA PROVINCIA DE CORDOBA S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            8 =>
            array(
                'id' => 9,
                'code' => 27,
                'name' => 'BANCO SUPERVIELLE S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            9 =>
            array(
                'id' => 10,
                'code' => 29,
                'name' => 'BANCO DE LA CIUDAD DE BUENOS AIRES',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            10 =>
            array(
                'id' => 11,
                'code' => 34,
                'name' => 'BANCO PATAGONIA S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            11 =>
            array(
                'id' => 12,
                'code' => 44,
                'name' => 'BANCO HIPOTECARIO S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            12 =>
            array(
                'id' => 13,
                'code' => 45,
                'name' => 'BANCO DE SAN JUAN S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            13 =>
            array(
                'id' => 14,
                'code' => 60,
                'name' => 'BANCO DEL TUCUMAN S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            14 =>
            array(
                'id' => 15,
                'code' => 65,
                'name' => 'BANCO MUNICIPAL DE ROSARIO',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            15 =>
            array(
                'id' => 16,
                'code' => 72,
                'name' => 'BANCO SANTANDER RIO S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            16 =>
            array(
                'id' => 17,
                'code' => 83,
                'name' => 'BANCO DEL CHUBUT S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            17 =>
            array(
                'id' => 18,
                'code' => 86,
                'name' => 'BANCO DE SANTA CRUZ S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            18 =>
            array(
                'id' => 19,
                'code' => 93,
                'name' => 'BANCO DE LA PAMPA SOCIEDAD DE ECONOMÍA M',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            19 =>
            array(
                'id' => 20,
                'code' => 94,
                'name' => 'BANCO DE CORRIENTES S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            20 =>
            array(
                'id' => 21,
                'code' => 97,
                'name' => 'BANCO PROVINCIA DEL NEUQUÉN SOCIEDAD ANÓ',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            21 =>
            array(
                'id' => 22,
                'code' => 147,
                'name' => 'BANCO INTERFINANZAS S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            22 =>
            array(
                'id' => 23,
                'code' => 150,
                'name' => 'HSBC BANK ARGENTINA S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            23 =>
            array(
                'id' => 24,
                'code' => 165,
                'name' => 'JPMORGAN CHASE BANK, NATIONAL ASSOCIATIO',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            24 =>
            array(
                'id' => 25,
                'code' => 191,
                'name' => 'BANCO CREDICOOP COOPERATIVO LIMITADO',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            25 =>
            array(
                'id' => 26,
                'code' => 198,
                'name' => 'BANCO DE VALORES S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            26 =>
            array(
                'id' => 27,
                'code' => 247,
                'name' => 'BANCO ROELA S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            27 =>
            array(
                'id' => 28,
                'code' => 254,
                'name' => 'BANCO MARIVA S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            28 =>
            array(
                'id' => 29,
                'code' => 259,
                'name' => 'BANCO ITAU ARGENTINA S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            29 =>
            array(
                'id' => 30,
                'code' => 262,
                'name' => 'BANK OF AMERICA, NATIONAL ASSOCIATION',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            30 =>
            array(
                'id' => 31,
                'code' => 266,
                'name' => 'BNP PARIBAS',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            31 =>
            array(
                'id' => 32,
                'code' => 268,
                'name' => 'BANCO PROVINCIA DE TIERRA DEL FUEGO',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            32 =>
            array(
                'id' => 33,
                'code' => 269,
                'name' => 'BANCO DE LA REPUBLICA ORIENTAL DEL URUGU',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            33 =>
            array(
                'id' => 34,
                'code' => 277,
                'name' => 'BANCO SAENZ S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            34 =>
            array(
                'id' => 35,
                'code' => 281,
                'name' => 'BANCO MERIDIAN S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            35 =>
            array(
                'id' => 36,
                'code' => 285,
                'name' => 'BANCO MACRO S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            36 =>
            array(
                'id' => 37,
                'code' => 299,
                'name' => 'BANCO COMAFI SOCIEDAD ANONIMA',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            37 =>
            array(
                'id' => 38,
                'code' => 300,
                'name' => 'BANCO DE INVERSION Y COMERCIO EXTERIOR S',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            38 =>
            array(
                'id' => 39,
                'code' => 301,
                'name' => 'BANCO PIANO S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            39 =>
            array(
                'id' => 40,
                'code' => 303,
                'name' => 'BANCO FINANSUR S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            40 =>
            array(
                'id' => 41,
                'code' => 305,
                'name' => 'BANCO JULIO SOCIEDAD ANONIMA',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            41 =>
            array(
                'id' => 42,
                'code' => 309,
                'name' => 'BANCO RIOJA SOCIEDAD ANONIMA UNIPERSONAL',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            42 =>
            array(
                'id' => 43,
                'code' => 310,
                'name' => 'BANCO DEL SOL S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            43 =>
            array(
                'id' => 44,
                'code' => 311,
                'name' => 'NUEVO BANCO DEL CHACO S. A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            44 =>
            array(
                'id' => 45,
                'code' => 312,
                'name' => 'BANCO VOII S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            45 =>
            array(
                'id' => 46,
                'code' => 315,
                'name' => 'BANCO DE FORMOSA S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            46 =>
            array(
                'id' => 47,
                'code' => 319,
                'name' => 'BANCO CMF S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            47 =>
            array(
                'id' => 48,
                'code' => 321,
                'name' => 'BANCO DE SANTIAGO DEL ESTERO S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            48 =>
            array(
                'id' => 49,
                'code' => 322,
                'name' => 'BANCO INDUSTRIAL S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            49 =>
            array(
                'id' => 50,
                'code' => 330,
                'name' => 'NUEVO BANCO DE SANTA FE SOCIEDAD ANONIMA',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            50 =>
            array(
                'id' => 51,
                'code' => 331,
                'name' => 'BANCO CETELEM ARGENTINA S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            51 =>
            array(
                'id' => 52,
                'code' => 332,
                'name' => 'BANCO DE SERVICIOS FINANCIEROS S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            52 =>
            array(
                'id' => 53,
                'code' => 336,
                'name' => 'BANCO BRADESCO ARGENTINA S.A.U.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            53 =>
            array(
                'id' => 54,
                'code' => 338,
                'name' => 'BANCO DE SERVICIOS Y TRANSACCIONES S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            54 =>
            array(
                'id' => 55,
                'code' => 339,
                'name' => 'RCI BANQUE S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            55 =>
            array(
                'id' => 56,
                'code' => 340,
                'name' => 'BACS BANCO DE CREDITO Y SECURITIZACION S',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            56 =>
            array(
                'id' => 57,
                'code' => 341,
                'name' => 'BANCO MASVENTAS S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            57 =>
            array(
                'id' => 58,
                'code' => 386,
                'name' => 'NUEVO BANCO DE ENTRE RÍOS S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            58 =>
            array(
                'id' => 59,
                'code' => 389,
                'name' => 'BANCO COLUMBIA S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            59 =>
            array(
                'id' => 60,
                'code' => 426,
                'name' => 'BANCO BICA S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            60 =>
            array(
                'id' => 61,
                'code' => 431,
                'name' => 'BANCO COINAG S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            61 =>
            array(
                'id' => 62,
                'code' => 432,
                'name' => 'BANCO DE COMERCIO S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            62 =>
            array(
                'id' => 63,
                'code' => 44059,
                'name' => 'FORD CREDIT COMPAÑIA FINANCIERA S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            63 =>
            array(
                'id' => 64,
                'code' => 44077,
                'name' => 'COMPAÑIA FINANCIERA ARGENTINA S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            64 =>
            array(
                'id' => 65,
                'code' => 44088,
                'name' => 'VOLKWAGEN FINANCIAL SERVICES CIA.FIN.S.A',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            65 =>
            array(
                'id' => 66,
                'code' => 44090,
                'name' => 'CORDIAL COMPAÑÍA FINANCIERA S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            66 =>
            array(
                'id' => 67,
                'code' => 44092,
                'name' => 'FCA COMPAÑIA FINANCIERA S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            67 =>
            array(
                'id' => 68,
                'code' => 44093,
                'name' => 'GPAT COMPAÑIA FINANCIERA S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            68 =>
            array(
                'id' => 69,
                'code' => 44094,
                'name' => 'MERCEDES-BENZ COMPAÑÍA FINANCIERA ARGENT',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            69 =>
            array(
                'id' => 70,
                'code' => 44095,
                'name' => 'ROMBO COMPAÑÍA FINANCIERA S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            70 =>
            array(
                'id' => 71,
                'code' => 44096,
                'name' => 'JOHN DEERE CREDIT COMPAÑÍA FINANCIERA S.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            71 =>
            array(
                'id' => 72,
                'code' => 44098,
                'name' => 'PSA FINANCE ARGENTINA COMPAÑÍA FINANCIER',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            72 =>
            array(
                'id' => 73,
                'code' => 44099,
                'name' => 'TOYOTA COMPAÑÍA FINANCIERA DE ARGENTINA',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            73 =>
            array(
                'id' => 74,
                'code' => 44100,
                'name' => 'FINANDINO COMPAÑIA FINANCIERA S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            74 =>
            array(
                'id' => 75,
                'code' => 45056,
                'name' => 'MONTEMAR COMPAÑIA FINANCIERA S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            75 =>
            array(
                'id' => 76,
                'code' => 45072,
                'name' => 'MULTIFINANZAS COMPAÑIA FINANCIERA S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            76 =>
            array(
                'id' => 77,
                'code' => 65203,
                'name' => 'CAJA DE CREDITO "CUENCA" COOPERATIVA LIM',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            77 =>
            array(
                'id' => 78,
                'code' => 99999,
                'name' => 'MERCADO PAGO',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            78 =>
            array(
                'id' => 79,
                'code' => 7,
                'name' => 'BANCO DE GALICIA Y BUENOS AIRES S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            79 =>
            array(
                'id' => 80,
                'code' => 11,
                'name' => 'BANCO DE LA NACION ARGENTINA',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            80 =>
            array(
                'id' => 81,
                'code' => 14,
                'name' => 'BANCO DE LA PROVINCIA DE BUENOS AIRES',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            81 =>
            array(
                'id' => 82,
                'code' => 15,
                'name' => 'INDUSTRIAL AND COMMERCIAL BANK OF CHINA',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            82 =>
            array(
                'id' => 83,
                'code' => 16,
                'name' => 'CITIBANK N.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            83 =>
            array(
                'id' => 84,
                'code' => 17,
                'name' => 'BBVA BANCO FRANCES S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            84 =>
            array(
                'id' => 85,
                'code' => 18,
                'name' => 'THE BANK OF TOKYO-MITSUBISHI UFJ, LTD.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            85 =>
            array(
                'id' => 86,
                'code' => 20,
                'name' => 'BANCO DE LA PROVINCIA DE CORDOBA S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            86 =>
            array(
                'id' => 87,
                'code' => 27,
                'name' => 'BANCO SUPERVIELLE S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            87 =>
            array(
                'id' => 88,
                'code' => 29,
                'name' => 'BANCO DE LA CIUDAD DE BUENOS AIRES',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            88 =>
            array(
                'id' => 89,
                'code' => 34,
                'name' => 'BANCO PATAGONIA S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            89 =>
            array(
                'id' => 90,
                'code' => 44,
                'name' => 'BANCO HIPOTECARIO S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            90 =>
            array(
                'id' => 91,
                'code' => 45,
                'name' => 'BANCO DE SAN JUAN S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            91 =>
            array(
                'id' => 92,
                'code' => 60,
                'name' => 'BANCO DEL TUCUMAN S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            92 =>
            array(
                'id' => 93,
                'code' => 65,
                'name' => 'BANCO MUNICIPAL DE ROSARIO',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            93 =>
            array(
                'id' => 94,
                'code' => 72,
                'name' => 'BANCO SANTANDER RIO S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            94 =>
            array(
                'id' => 95,
                'code' => 83,
                'name' => 'BANCO DEL CHUBUT S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            95 =>
            array(
                'id' => 96,
                'code' => 86,
                'name' => 'BANCO DE SANTA CRUZ S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            96 =>
            array(
                'id' => 97,
                'code' => 93,
                'name' => 'BANCO DE LA PAMPA SOCIEDAD DE ECONOMÍA M',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            97 =>
            array(
                'id' => 98,
                'code' => 94,
                'name' => 'BANCO DE CORRIENTES S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            98 =>
            array(
                'id' => 99,
                'code' => 97,
                'name' => 'BANCO PROVINCIA DEL NEUQUÉN SOCIEDAD ANÓ',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            99 =>
            array(
                'id' => 100,
                'code' => 147,
                'name' => 'BANCO INTERFINANZAS S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            100 =>
            array(
                'id' => 101,
                'code' => 150,
                'name' => 'HSBC BANK ARGENTINA S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            101 =>
            array(
                'id' => 102,
                'code' => 165,
                'name' => 'JPMORGAN CHASE BANK, NATIONAL ASSOCIATIO',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            102 =>
            array(
                'id' => 103,
                'code' => 191,
                'name' => 'BANCO CREDICOOP COOPERATIVO LIMITADO',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            103 =>
            array(
                'id' => 104,
                'code' => 198,
                'name' => 'BANCO DE VALORES S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            104 =>
            array(
                'id' => 105,
                'code' => 247,
                'name' => 'BANCO ROELA S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            105 =>
            array(
                'id' => 106,
                'code' => 254,
                'name' => 'BANCO MARIVA S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            106 =>
            array(
                'id' => 107,
                'code' => 259,
                'name' => 'BANCO ITAU ARGENTINA S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            107 =>
            array(
                'id' => 108,
                'code' => 262,
                'name' => 'BANK OF AMERICA, NATIONAL ASSOCIATION',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            108 =>
            array(
                'id' => 109,
                'code' => 266,
                'name' => 'BNP PARIBAS',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            109 =>
            array(
                'id' => 110,
                'code' => 268,
                'name' => 'BANCO PROVINCIA DE TIERRA DEL FUEGO',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            110 =>
            array(
                'id' => 111,
                'code' => 269,
                'name' => 'BANCO DE LA REPUBLICA ORIENTAL DEL URUGU',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            111 =>
            array(
                'id' => 112,
                'code' => 277,
                'name' => 'BANCO SAENZ S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            112 =>
            array(
                'id' => 113,
                'code' => 281,
                'name' => 'BANCO MERIDIAN S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            113 =>
            array(
                'id' => 114,
                'code' => 285,
                'name' => 'BANCO MACRO S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            114 =>
            array(
                'id' => 115,
                'code' => 299,
                'name' => 'BANCO COMAFI SOCIEDAD ANONIMA',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            115 =>
            array(
                'id' => 116,
                'code' => 300,
                'name' => 'BANCO DE INVERSION Y COMERCIO EXTERIOR S',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            116 =>
            array(
                'id' => 117,
                'code' => 301,
                'name' => 'BANCO PIANO S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            117 =>
            array(
                'id' => 118,
                'code' => 303,
                'name' => 'BANCO FINANSUR S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            118 =>
            array(
                'id' => 119,
                'code' => 305,
                'name' => 'BANCO JULIO SOCIEDAD ANONIMA',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            119 =>
            array(
                'id' => 120,
                'code' => 309,
                'name' => 'BANCO RIOJA SOCIEDAD ANONIMA UNIPERSONAL',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            120 =>
            array(
                'id' => 121,
                'code' => 310,
                'name' => 'BANCO DEL SOL S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            121 =>
            array(
                'id' => 122,
                'code' => 311,
                'name' => 'NUEVO BANCO DEL CHACO S. A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            122 =>
            array(
                'id' => 123,
                'code' => 312,
                'name' => 'BANCO VOII S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            123 =>
            array(
                'id' => 124,
                'code' => 315,
                'name' => 'BANCO DE FORMOSA S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            124 =>
            array(
                'id' => 125,
                'code' => 319,
                'name' => 'BANCO CMF S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            125 =>
            array(
                'id' => 126,
                'code' => 321,
                'name' => 'BANCO DE SANTIAGO DEL ESTERO S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            126 =>
            array(
                'id' => 127,
                'code' => 322,
                'name' => 'BANCO INDUSTRIAL S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            127 =>
            array(
                'id' => 128,
                'code' => 330,
                'name' => 'NUEVO BANCO DE SANTA FE SOCIEDAD ANONIMA',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            128 =>
            array(
                'id' => 129,
                'code' => 331,
                'name' => 'BANCO CETELEM ARGENTINA S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            129 =>
            array(
                'id' => 130,
                'code' => 332,
                'name' => 'BANCO DE SERVICIOS FINANCIEROS S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            130 =>
            array(
                'id' => 131,
                'code' => 336,
                'name' => 'BANCO BRADESCO ARGENTINA S.A.U.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            131 =>
            array(
                'id' => 132,
                'code' => 338,
                'name' => 'BANCO DE SERVICIOS Y TRANSACCIONES S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            132 =>
            array(
                'id' => 133,
                'code' => 339,
                'name' => 'RCI BANQUE S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            133 =>
            array(
                'id' => 134,
                'code' => 340,
                'name' => 'BACS BANCO DE CREDITO Y SECURITIZACION S',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            134 =>
            array(
                'id' => 135,
                'code' => 341,
                'name' => 'BANCO MASVENTAS S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            135 =>
            array(
                'id' => 136,
                'code' => 386,
                'name' => 'NUEVO BANCO DE ENTRE RÍOS S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            136 =>
            array(
                'id' => 137,
                'code' => 389,
                'name' => 'BANCO COLUMBIA S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            137 =>
            array(
                'id' => 138,
                'code' => 426,
                'name' => 'BANCO BICA S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            138 =>
            array(
                'id' => 139,
                'code' => 431,
                'name' => 'BANCO COINAG S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            139 =>
            array(
                'id' => 140,
                'code' => 432,
                'name' => 'BANCO DE COMERCIO S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            140 =>
            array(
                'id' => 141,
                'code' => 44059,
                'name' => 'FORD CREDIT COMPAÑIA FINANCIERA S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            141 =>
            array(
                'id' => 142,
                'code' => 44077,
                'name' => 'COMPAÑIA FINANCIERA ARGENTINA S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            142 =>
            array(
                'id' => 143,
                'code' => 44088,
                'name' => 'VOLKWAGEN FINANCIAL SERVICES CIA.FIN.S.A',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            143 =>
            array(
                'id' => 144,
                'code' => 44090,
                'name' => 'CORDIAL COMPAÑÍA FINANCIERA S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            144 =>
            array(
                'id' => 145,
                'code' => 44092,
                'name' => 'FCA COMPAÑIA FINANCIERA S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            145 =>
            array(
                'id' => 146,
                'code' => 44093,
                'name' => 'GPAT COMPAÑIA FINANCIERA S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            146 =>
            array(
                'id' => 147,
                'code' => 44094,
                'name' => 'MERCEDES-BENZ COMPAÑÍA FINANCIERA ARGENT',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            147 =>
            array(
                'id' => 148,
                'code' => 44095,
                'name' => 'ROMBO COMPAÑÍA FINANCIERA S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            148 =>
            array(
                'id' => 149,
                'code' => 44096,
                'name' => 'JOHN DEERE CREDIT COMPAÑÍA FINANCIERA S.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            149 =>
            array(
                'id' => 150,
                'code' => 44098,
                'name' => 'PSA FINANCE ARGENTINA COMPAÑÍA FINANCIER',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            150 =>
            array(
                'id' => 151,
                'code' => 44099,
                'name' => 'TOYOTA COMPAÑÍA FINANCIERA DE ARGENTINA',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            151 =>
            array(
                'id' => 152,
                'code' => 44100,
                'name' => 'FINANDINO COMPAÑIA FINANCIERA S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            152 =>
            array(
                'id' => 153,
                'code' => 45056,
                'name' => 'MONTEMAR COMPAÑIA FINANCIERA S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            153 =>
            array(
                'id' => 154,
                'code' => 45072,
                'name' => 'MULTIFINANZAS COMPAÑIA FINANCIERA S.A.',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            154 =>
            array(
                'id' => 155,
                'code' => 65203,
                'name' => 'CAJA DE CREDITO "CUENCA" COOPERATIVA LIM',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            155 =>
            array(
                'id' => 156,
                'code' => 99999,
                'name' => 'MERCADO PAGO',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
        ));
    }
}
