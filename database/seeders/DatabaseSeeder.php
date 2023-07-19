<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        /**Afip Seeder */
        $this->call(AfipInscriptionSeeder::class);
        $this->call(AfipIvasTableSeeder::class);
        $this->call(AfipMoneyTableSeeder::class);
        $this->call(AfipStatesTableSeeder::class);
        $this->call(AfipVouchersTableSeeder::class);

        $this->call(BanksTableSeeder::class);
        $this->call(RolesTableSeeder::class);
        $this->call(PermissionsTableSeeder::class);
    }
}
