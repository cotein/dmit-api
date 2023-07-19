<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AfipIvasTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {


        DB::table('ivas')->delete();

        DB::table('ivas')->insert(array(
            0 =>
            array(
                'id' => 1,
                'code' => '0',
                'name' => 'No Corresponde',
                'percentage' => '0',
                'created_at' => '2020-09-07 16:12:21',
                'updated_at' => '2020-09-07 16:12:21',
                'inscription_id' => NULL,
            ),
            1 =>
            array(
                'id' => 2,
                'code' => '1',
                'name' => 'No Gravado',
                'percentage' => '0',
                'created_at' => '2020-09-07 16:12:21',
                'updated_at' => '2020-09-07 16:12:21',
                'inscription_id' => NULL,
            ),
            2 =>
            array(
                'id' => 3,
                'code' => '2',
                'name' => 'Exento',
                'percentage' => '0',
                'created_at' => '2020-09-07 16:12:21',
                'updated_at' => '2020-09-07 16:12:21',
                'inscription_id' => NULL,
            ),
            3 =>
            array(
                'id' => 4,
                'code' => '3',
                'name' => '0%',
                'percentage' => '0',
                'created_at' => '2020-09-07 16:12:21',
                'updated_at' => '2020-09-07 16:12:21',
                'inscription_id' => NULL,
            ),
            4 =>
            array(
                'id' => 5,
                'code' => '4',
                'name' => '10,50%',
                'percentage' => '10.5',
                'created_at' => '2020-09-07 16:12:21',
                'updated_at' => '2020-09-07 16:12:21',
                'inscription_id' => 1,
            ),
            5 =>
            array(
                'id' => 6,
                'code' => '5',
                'name' => '21%',
                'percentage' => '21',
                'created_at' => '2020-09-07 16:12:21',
                'updated_at' => '2020-09-07 16:12:21',
                'inscription_id' => 1,
            ),
            6 =>
            array(
                'id' => 7,
                'code' => '6',
                'name' => '27%',
                'percentage' => '27',
                'created_at' => '2020-09-07 16:12:21',
                'updated_at' => '2020-09-07 16:12:21',
                'inscription_id' => 1,
            ),
            7 =>
            array(
                'id' => 8,
                'code' => '7',
                'name' => 'Gravado',
                'percentage' => '0',
                'created_at' => '2020-09-07 16:12:21',
                'updated_at' => '2020-09-07 16:12:21',
                'inscription_id' => 1,
            ),
            8 =>
            array(
                'id' => 9,
                'code' => '8',
                'name' => '5%',
                'percentage' => '5',
                'created_at' => '2020-09-07 16:12:21',
                'updated_at' => '2020-09-07 16:12:21',
                'inscription_id' => 1,
            ),
            9 =>
            array(
                'id' => 10,
                'code' => '9',
                'name' => '2,50%',
                'percentage' => '2.5',
                'created_at' => '2020-09-07 16:12:21',
                'updated_at' => '2020-09-07 16:12:21',
                'inscription_id' => 1,
            ),
        ));
    }
}
