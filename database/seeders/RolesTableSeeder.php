<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class RolesTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {


        \DB::table('roles')->delete();

        \DB::table('roles')->insert(array(
            0 =>
            array(
                'id' => 1,
                'name' => 'CONTADOR',
                'guard_name' => 'api',
                'created_at' => '2023-07-15 13:30:02',
                'updated_at' => '2023-07-15 13:30:02',
            ),
            1 =>
            array(
                'id' => 2,
                'name' => 'COMPAÑIA',
                'guard_name' => 'api',
                'created_at' => '2023-07-15 13:30:02',
                'updated_at' => '2023-07-15 13:30:02',
            ),
            2 =>
            array(
                'id' => 3,
                'name' => 'USUARIO',
                'guard_name' => 'api',
                'created_at' => '2023-07-15 13:50:39',
                'updated_at' => '2023-07-15 13:50:39',
            ),
        ));
    }
}
