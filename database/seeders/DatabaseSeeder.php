<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use App\Src\Constantes;
use Illuminate\Database\Seeder;
use Database\Factories\StatusFactory;
use Database\Factories\CompanyFactory;
use Database\Factories\CompanyTypeFactory;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        CompanyTypeFactory::times(1)->create(['name' => 'JURÍDICA']);
        CompanyTypeFactory::times(1)->create(['name' => 'FÍSICA']);

        StatusFactory::times(1)->create(['name' => 'ADEUDA', 'level' => 100]);
        StatusFactory::times(1)->create(['name' => 'PARCIALMENTE CANCELADA', 'level' => 200]);
        StatusFactory::times(1)->create(['name' => 'CANCELADA', 'level' => 300]);

        /**Afip Seeder */
        $this->call(AfipInscriptionSeeder::class);
        $this->call(AfipIvasTableSeeder::class);
        $this->call(AfipMoneyTableSeeder::class);
        $this->call(AfipStatesTableSeeder::class);
        $this->call(AfipVouchersTableSeeder::class);
        $this->call(AfipDocumentsTableSeeder::class);

        /** Others seeders */
        $this->call(BanksTableSeeder::class);
        $this->call(RolesTableSeeder::class);
        $this->call(PermissionsTableSeeder::class);
        $this->call(AfipDocumentsTableSeeder::class);
        $this->call(CompanyBillingConceptsTableSeeder::class);
        $this->call(SaleConditionSeeder::class);
        $this->call(PaymentTypeSeeder::class);
        $this->call(AccountingAccountTableSeeder::class);
        $this->call(VouchersTableSeeder::class);
    }
}
