<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AfipMoneyTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {


        DB::table('moneys')->delete();

        DB::table('moneys')->insert(array(
            0 =>
            array(
                'id' => 1,
                'code' => '000',
                'name' => 'OTRAS MONEDAS ',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            1 =>
            array(
                'id' => 2,
                'code' => 'PES ',
                'name' => 'PESOS ',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            2 =>
            array(
                'id' => 3,
                'code' => 'DOL ',
                'name' => 'Dólar ESTADOUNIDENSE ',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            3 =>
            array(
                'id' => 4,
                'code' => '002',
                'name' => 'Dólar EEUU LIBRE ',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            4 =>
            array(
                'id' => 5,
                'code' => '003',
                'name' => 'FRANCOS FRANCESES ',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            5 =>
            array(
                'id' => 6,
                'code' => '004',
                'name' => 'LIRAS ITALIANAS ',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            6 =>
            array(
                'id' => 7,
                'code' => '005',
                'name' => 'PESETAS ',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            7 =>
            array(
                'id' => 8,
                'code' => '006',
                'name' => 'MARCOS ALEMANES ',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            8 =>
            array(
                'id' => 9,
                'code' => '007',
                'name' => 'FLORINES HOLANDESES ',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            9 =>
            array(
                'id' => 10,
                'code' => '008',
                'name' => 'FRANCOS BELGAS ',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            10 =>
            array(
                'id' => 11,
                'code' => '009',
                'name' => 'FRANCOS SUIZOS ',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            11 =>
            array(
                'id' => 12,
                'code' => '010',
                'name' => 'PESOS MEJICANOS ',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            12 =>
            array(
                'id' => 13,
                'code' => '011',
                'name' => 'PESOS URUGUAYOS ',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            13 =>
            array(
                'id' => 14,
                'code' => '012',
                'name' => 'REAL ',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            14 =>
            array(
                'id' => 15,
                'code' => '013',
                'name' => 'ESCUDOS PORTUGUESES ',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            15 =>
            array(
                'id' => 16,
                'code' => '014',
                'name' => 'CORONAS DANESAS ',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            16 =>
            array(
                'id' => 17,
                'code' => '015',
                'name' => 'CORONAS NORUEGAS ',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            17 =>
            array(
                'id' => 18,
                'code' => '016',
                'name' => 'CORONAS SUECAS ',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            18 =>
            array(
                'id' => 19,
                'code' => '017',
                'name' => 'CHELINES AUTRIACOS ',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            19 =>
            array(
                'id' => 20,
                'code' => '018',
                'name' => 'Dólar CANADIENSE ',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            20 =>
            array(
                'id' => 21,
                'code' => '019',
                'name' => 'YENS ',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            21 =>
            array(
                'id' => 22,
                'code' => '021',
                'name' => 'LIBRA ESTERLINA ',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            22 =>
            array(
                'id' => 23,
                'code' => '022',
                'name' => 'MARCOS FINLANDESES ',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            23 =>
            array(
                'id' => 24,
                'code' => '023',
                'name' => 'BOLIVAR (VENEZOLANO)',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            24 =>
            array(
                'id' => 25,
                'code' => '024',
                'name' => 'CORONA CHECA ',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            25 =>
            array(
                'id' => 26,
                'code' => '025',
                'name' => 'DINAR (YUGOSLAVO) ',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            26 =>
            array(
                'id' => 27,
                'code' => '026',
                'name' => 'Dólar AUSTRALIANO ',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            27 =>
            array(
                'id' => 28,
                'code' => '027',
                'name' => 'DRACMA (GRIEGO) ',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            28 =>
            array(
                'id' => 29,
                'code' => '028',
                'name' => 'FLORIN (ANTILLAS HOLA ',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            29 =>
            array(
                'id' => 30,
                'code' => '029',
                'name' => 'GUARANI ',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            30 =>
            array(
                'id' => 31,
                'code' => '030',
                'name' => 'SHEKEL (ISRAEL) ',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            31 =>
            array(
                'id' => 32,
                'code' => '031',
                'name' => 'PESO BOLIVIANO ',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            32 =>
            array(
                'id' => 33,
                'code' => '032',
                'name' => 'PESO COLOMBIANO ',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            33 =>
            array(
                'id' => 34,
                'code' => '033',
                'name' => 'PESO CHILENO ',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            34 =>
            array(
                'id' => 35,
                'code' => '034',
                'name' => 'RAND (SUDAFRICANO)',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            35 =>
            array(
                'id' => 36,
                'code' => '035',
                'name' => 'NUEVO SOL PERUANO ',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            36 =>
            array(
                'id' => 37,
                'code' => '036',
                'name' => 'SUCRE (ECUATORIANO) ',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            37 =>
            array(
                'id' => 38,
                'code' => '040',
                'name' => 'LEI RUMANOS ',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            38 =>
            array(
                'id' => 39,
                'code' => '041',
                'name' => 'DERECHOS ESPECIALES DE GIRO ',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            39 =>
            array(
                'id' => 40,
                'code' => '042',
                'name' => 'PESOS DOMINICANOS ',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            40 =>
            array(
                'id' => 41,
                'code' => '043',
                'name' => 'BALBOAS PANAMEÑAS ',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            41 =>
            array(
                'id' => 42,
                'code' => '044',
                'name' => 'CORDOBAS NICARAGÛENSES ',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            42 =>
            array(
                'id' => 43,
                'code' => '045',
                'name' => 'DIRHAM MARROQUÍES ',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            43 =>
            array(
                'id' => 44,
                'code' => '046',
                'name' => 'LIBRAS EGIPCIAS ',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            44 =>
            array(
                'id' => 45,
                'code' => '047',
                'name' => 'RIYALS SAUDITAS ',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            45 =>
            array(
                'id' => 46,
                'code' => '048',
                'name' => 'BRANCOS BELGAS FINANCIERAS',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            46 =>
            array(
                'id' => 47,
                'code' => '049',
                'name' => 'GRAMOS DE ORO FINO ',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            47 =>
            array(
                'id' => 48,
                'code' => '050',
                'name' => 'LIBRAS IRLANDESAS ',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            48 =>
            array(
                'id' => 49,
                'code' => '051',
                'name' => 'Dólar DE HONG KONG ',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            49 =>
            array(
                'id' => 50,
                'code' => '052',
                'name' => 'Dólar DE SINGAPUR ',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            50 =>
            array(
                'id' => 51,
                'code' => '053',
                'name' => 'Dólar DE JAMAICA ',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            51 =>
            array(
                'id' => 52,
                'code' => '054',
                'name' => 'Dólar DE TAIWAN ',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            52 =>
            array(
                'id' => 53,
                'code' => '055',
                'name' => 'QUETZAL (GUATEMALTECOS) ',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            53 =>
            array(
                'id' => 54,
                'code' => '056',
                'name' => 'FORINT (HUNGRIA) ',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            54 =>
            array(
                'id' => 55,
                'code' => '057',
                'name' => 'BAHT (TAILANDIA) ',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            55 =>
            array(
                'id' => 56,
                'code' => '058',
                'name' => 'ECU ',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            56 =>
            array(
                'id' => 57,
                'code' => '059',
                'name' => 'DINAR KUWAITI ',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            57 =>
            array(
                'id' => 58,
                'code' => '060',
                'name' => 'EURO ',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            58 =>
            array(
                'id' => 59,
                'code' => '061',
                'name' => 'ZLTYS POLACOS ',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            59 =>
            array(
                'id' => 60,
                'code' => '062',
                'name' => 'RUPIAS HINDÚES ',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            60 =>
            array(
                'id' => 61,
                'code' => '063',
                'name' => 'LEMPIRAS HONDUREÑAS ',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            61 =>
            array(
                'id' => 62,
                'code' => '064',
                'name' => 'YUAN (Rep. Pop. China)',
                'symbol' => '',
                'value' => '',
                'description' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
        ));
    }
}
