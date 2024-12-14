<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class TypeUsersTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('type_users')->delete();
        
        \DB::table('type_users')->insert(array (
            0 => 
            array (
                'id' => 1,
                'name' => 'ROOT',
                'created_at' => '2024-06-10 11:46:27',
                'updated_at' => '2024-06-10 11:46:27',
                'level' => 1000,
            ),
            1 => 
            array (
                'id' => 2,
                'name' => 'ADMINISTRADOR',
                'created_at' => '2024-06-10 11:46:27',
                'updated_at' => '2024-06-10 11:46:27',
                'level' => 900,
            ),
            2 => 
            array (
                'id' => 3,
                'name' => 'USUARIO',
                'created_at' => '2024-06-10 11:46:27',
                'updated_at' => '2024-06-10 11:46:27',
                'level' => 100,
            ),
        ));
        
        
    }
}