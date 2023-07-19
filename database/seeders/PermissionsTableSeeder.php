<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class PermissionsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('permissions')->delete();
        
        \DB::table('permissions')->insert(array (
            0 => 
            array (
                'id' => 1,
                'name' => 'FACTURAR',
                'guard_name' => 'web',
                'created_at' => '2023-07-15 13:50:39',
                'updated_at' => '2023-07-15 13:50:39',
            ),
            1 => 
            array (
                'id' => 2,
                'name' => 'CREAR COMPAÑIA',
                'guard_name' => 'web',
                'created_at' => '2023-07-15 13:50:39',
                'updated_at' => '2023-07-15 13:50:39',
            ),
            2 => 
            array (
                'id' => 3,
                'name' => 'ELIMINAR FACTURA',
                'guard_name' => 'web',
                'created_at' => '2023-07-15 13:50:39',
                'updated_at' => '2023-07-15 13:50:39',
            ),
            3 => 
            array (
                'id' => 4,
                'name' => 'CREAR USUARIO',
                'guard_name' => 'web',
                'created_at' => '2023-07-15 13:50:39',
                'updated_at' => '2023-07-15 13:50:39',
            ),
        ));
        
        
    }
}