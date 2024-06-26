<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CompanyBillingConceptsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {

        DB::table('company_billing_concepts')->truncate();

        DB::table('company_billing_concepts')->insert(array(
            0 =>
            array(
                'id' => 3,
                'name' => 'PRODUCTOS',
                'updated_at' => '2023-09-29 17:33:32',
            ),
            1 =>
            array(
                'id' => 4,
                'name' => 'SERVICIOS',
                'updated_at' => '2023-09-29 17:33:32',
            ),
            2 =>
            array(
                'id' => 5,
                'name' => 'PRODUCTOS Y SERVICIOS',
                'updated_at' => '2023-09-29 17:33:32',
            ),
        ));
    }
}
