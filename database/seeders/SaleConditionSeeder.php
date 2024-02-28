<?php

namespace Database\Seeders;

use App\Models\SaleCondition;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SaleConditionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $salesCondition = [

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

        ];

        $salesCondition = collect($salesCondition);

        $salesCondition->each(function ($sc) {
            SaleCondition::create([
                'name'     => $sc['name'],
                'days' => $sc['days'],
            ]);
        });
    }
}
