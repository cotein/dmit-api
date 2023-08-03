<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $company = new Company;
        $company->name = 'DMIT';
        $company->last_name = '';
        $company->fantasy_name = 'DMIT';
        $company->dni = 22733973;
        $company->afip_number = 20227339730;
        $company->save();

        $DmItUser = new User;
        $DmItUser->name = 'DMIT';
        $DmItUser->last_name = '';
        $DmItUser->email = 'dmit@gmail.com';
        $DmItUser->password = Hash::make('secret');
        $DmItUser->active = true;
        $DmItUser->company_id = 1;
        $DmItUser->save();

        /**Afip Seeder */
        $this->call(AfipInscriptionSeeder::class);
        $this->call(AfipIvasTableSeeder::class);
        $this->call(AfipMoneyTableSeeder::class);
        $this->call(AfipStatesTableSeeder::class);
        $this->call(AfipVouchersTableSeeder::class);

        /** Others seeders */
        $this->call(BanksTableSeeder::class);
        $this->call(RolesTableSeeder::class);
        $this->call(PermissionsTableSeeder::class);
    }
}
