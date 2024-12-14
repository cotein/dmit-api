<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Database\Factories\PriceListProductFactory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class PriceListProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        PriceListProductFactory::times(100)->create();
    }
}
