<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Seeder;

class AfipDocumentsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {

        DB::table('afip_documents')->truncate();

        DB::table('afip_documents')->insert(array(
            0 =>
            array(
                'afip_code' => '0',
                'created_at' => NULL,
                'id' => 1,
                'name' => 'CI Policía Federal',
                'updated_at' => NULL,
            ),
            1 =>
            array(
                'afip_code' => '1',
                'created_at' => NULL,
                'id' => 2,
                'name' => 'CI Buenos Aires',
                'updated_at' => NULL,
            ),
            2 =>
            array(
                'afip_code' => '2',
                'created_at' => NULL,
                'id' => 3,
                'name' => 'CI Catamarca',
                'updated_at' => NULL,
            ),
            3 =>
            array(
                'afip_code' => '3',
                'created_at' => NULL,
                'id' => 4,
                'name' => 'CI Córdoba',
                'updated_at' => NULL,
            ),
            4 =>
            array(
                'afip_code' => '4',
                'created_at' => NULL,
                'id' => 5,
                'name' => 'CI Corrientes',
                'updated_at' => NULL,
            ),
            5 =>
            array(
                'afip_code' => '5',
                'created_at' => NULL,
                'id' => 6,
                'name' => 'CI Entre Ríos',
                'updated_at' => NULL,
            ),
            6 =>
            array(
                'afip_code' => '6',
                'created_at' => NULL,
                'id' => 7,
                'name' => 'CI Jujuy',
                'updated_at' => NULL,
            ),
            7 =>
            array(
                'afip_code' => '7',
                'created_at' => NULL,
                'id' => 8,
                'name' => 'CI Mendoza',
                'updated_at' => NULL,
            ),
            8 =>
            array(
                'afip_code' => '8',
                'created_at' => NULL,
                'id' => 9,
                'name' => 'CI La Rioja',
                'updated_at' => NULL,
            ),
            9 =>
            array(
                'afip_code' => '9',
                'created_at' => NULL,
                'id' => 10,
                'name' => 'CI Salta',
                'updated_at' => NULL,
            ),
            10 =>
            array(
                'afip_code' => '10',
                'created_at' => NULL,
                'id' => 11,
                'name' => 'CI San Juan',
                'updated_at' => NULL,
            ),
            11 =>
            array(
                'afip_code' => '11',
                'created_at' => NULL,
                'id' => 12,
                'name' => 'CI San Luis',
                'updated_at' => NULL,
            ),
            12 =>
            array(
                'afip_code' => '12',
                'created_at' => NULL,
                'id' => 13,
                'name' => 'CI Santa Fe',
                'updated_at' => NULL,
            ),
            13 =>
            array(
                'afip_code' => '13',
                'created_at' => NULL,
                'id' => 14,
                'name' => 'CI Santiago del Estero',
                'updated_at' => NULL,
            ),
            14 =>
            array(
                'afip_code' => '14',
                'created_at' => NULL,
                'id' => 15,
                'name' => 'CI Tucumán',
                'updated_at' => NULL,
            ),
            15 =>
            array(
                'afip_code' => '16',
                'created_at' => NULL,
                'id' => 16,
                'name' => 'CI Chaco',
                'updated_at' => NULL,
            ),
            16 =>
            array(
                'afip_code' => '17',
                'created_at' => NULL,
                'id' => 17,
                'name' => 'CI Chubut',
                'updated_at' => NULL,
            ),
            17 =>
            array(
                'afip_code' => '18',
                'created_at' => NULL,
                'id' => 18,
                'name' => 'CI Formosa',
                'updated_at' => NULL,
            ),
            18 =>
            array(
                'afip_code' => '19',
                'created_at' => NULL,
                'id' => 19,
                'name' => 'CI Misiones',
                'updated_at' => NULL,
            ),
            19 =>
            array(
                'afip_code' => '20',
                'created_at' => NULL,
                'id' => 20,
                'name' => 'CI Neuquén',
                'updated_at' => NULL,
            ),
            20 =>
            array(
                'afip_code' => '21',
                'created_at' => NULL,
                'id' => 21,
                'name' => 'CI La Pampa',
                'updated_at' => NULL,
            ),
            21 =>
            array(
                'afip_code' => '22',
                'created_at' => NULL,
                'id' => 22,
                'name' => 'CI Río Negro',
                'updated_at' => NULL,
            ),
            22 =>
            array(
                'afip_code' => '23',
                'created_at' => NULL,
                'id' => 23,
                'name' => 'CI Santa Cruz',
                'updated_at' => NULL,
            ),
            23 =>
            array(
                'afip_code' => '24',
                'created_at' => NULL,
                'id' => 24,
                'name' => 'CI Tierra del Fuego',
                'updated_at' => NULL,
            ),
            24 =>
            array(
                'afip_code' => '80',
                'created_at' => NULL,
                'id' => 25,
                'name' => 'CUIT',
                'updated_at' => NULL,
            ),
            25 =>
            array(
                'afip_code' => '86',
                'created_at' => NULL,
                'id' => 26,
                'name' => 'CUIL',
                'updated_at' => NULL,
            ),
            26 =>
            array(
                'afip_code' => '87',
                'created_at' => NULL,
                'id' => 27,
                'name' => 'CDI',
                'updated_at' => NULL,
            ),
            27 =>
            array(
                'afip_code' => '89',
                'created_at' => NULL,
                'id' => 28,
                'name' => 'LE',
                'updated_at' => NULL,
            ),
            28 =>
            array(
                'afip_code' => '90',
                'created_at' => NULL,
                'id' => 29,
                'name' => 'LC',
                'updated_at' => NULL,
            ),
            29 =>
            array(
                'afip_code' => '91',
                'created_at' => NULL,
                'id' => 30,
                'name' => 'CI extranjera',
                'updated_at' => NULL,
            ),
            30 =>
            array(
                'afip_code' => '92',
                'created_at' => NULL,
                'id' => 31,
                'name' => 'en trámite',
                'updated_at' => NULL,
            ),
            31 =>
            array(
                'afip_code' => '93',
                'created_at' => NULL,
                'id' => 32,
                'name' => 'Acta nacimiento',
                'updated_at' => NULL,
            ),
            32 =>
            array(
                'afip_code' => '94',
                'created_at' => NULL,
                'id' => 33,
                'name' => 'Pasaporte',
                'updated_at' => NULL,
            ),
            33 =>
            array(
                'afip_code' => '95',
                'created_at' => NULL,
                'id' => 34,
                'name' => 'CI Bs. As. RNP',
                'updated_at' => NULL,
            ),
            34 =>
            array(
                'afip_code' => '96',
                'created_at' => NULL,
                'id' => 35,
                'name' => 'DNI',
                'updated_at' => NULL,
            ),
            35 =>
            array(
                'afip_code' => '99',
                'created_at' => NULL,
                'id' => 36,
                'name' => 'Sin identificar/venta global diaria',
                'updated_at' => NULL,
            ),
            36 =>
            array(
                'afip_code' => '30',
                'created_at' => NULL,
                'id' => 37,
                'name' => 'Certificado de Migración',
                'updated_at' => NULL,
            ),
            37 =>
            array(
                'afip_code' => '88',
                'created_at' => NULL,
                'id' => 38,
                'name' => 'Usado por Anses para Padrón',
                'updated_at' => NULL,
            ),
        ));
    }
}
