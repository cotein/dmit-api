<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class PaymentTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('payment_types')->truncate();

        DB::table('payment_types')->insert([
            [
                'company_id' => 1,
                'name' => 'EFECTIVO',
                'percentage' => 0,
                'active' => true
            ],
            [
                'company_id' => 1,
                'name' => 'TRANSFERENCIA',
                'percentage' => 10,
                'active' => true
            ],
            [
                'company_id' => 1,
                'name' => 'MERCADO PAGO',
                'percentage' => 3,
                'active' => true
            ],
        ]);
    }
}
