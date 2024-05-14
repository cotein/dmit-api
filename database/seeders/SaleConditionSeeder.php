<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SaleConditionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('sale_conditions')->truncate();

        DB::table('sale_conditions')->insert([
            [
                'name'  => 'Contado',
                'days' => 0,
            ],
            [
                'name'  => '7 Días fecha factura',
                'days' => 7,
            ],
            [
                'name'  => '15 Días fecha factura',
                'days' => 15,
            ],
            [
                'name'  => '30 Días fecha factura',
                'days' => 30,
            ],
            [
                'name'  => 'Notas de crédito y débito',
                'days' => 300000,
            ],

        ]);
    }
}
