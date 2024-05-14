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


        DB::table('ivas')->truncate();

        DB::table('ivas')->insert([
            [
                'code' => '3',
                'name' => '0%',
                'percentage' => '0',
                'created_at' => '2020-09-07 16:12:21',
                'updated_at' => '2020-09-07 16:12:21',
                'inscription_id' => NULL,
            ],
            [
                'code' => '4',
                'name' => '10,50%',
                'percentage' => '10.5',
                'created_at' => '2020-09-07 16:12:21',
                'updated_at' => '2020-09-07 16:12:21',
                'inscription_id' => 1,
            ],
            [
                'code' => '5',
                'name' => '21%',
                'percentage' => '21',
                'created_at' => '2020-09-07 16:12:21',
                'updated_at' => '2020-09-07 16:12:21',
                'inscription_id' => 1,
            ],
            [
                'code' => '6',
                'name' => '27%',
                'percentage' => '27',
                'created_at' => '2020-09-07 16:12:21',
                'updated_at' => '2020-09-07 16:12:21',
                'inscription_id' => 1,
            ],
            [
                'code' => '8',
                'name' => '5%',
                'percentage' => '5',
                'created_at' => '2020-09-07 16:12:21',
                'updated_at' => '2020-09-07 16:12:21',
                'inscription_id' => 1,
            ],
            [
                'code' => '9',
                'name' => '2,50%',
                'percentage' => '2.5',
                'created_at' => '2020-09-07 16:12:21',
                'updated_at' => '2020-09-07 16:12:21',
                'inscription_id' => 1,
            ],
        ]);
    }
}
