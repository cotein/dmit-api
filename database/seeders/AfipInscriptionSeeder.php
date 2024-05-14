<?php

namespace Database\Seeders;

use App\Models\AfipInscription;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AfipInscriptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('afip_inscriptions')->truncate();

        DB::table('afip_inscriptions')->insert(
            [
                [
                    'name'  => 'IVA Responsable Inscripto',
                    'short' => 'IRI',
                ],
                [
                    'name'  => 'IVA Responsable no Inscripto',
                    'short' => 'IRNI',
                ],
                [
                    'name'  => 'IVA no Responsable',
                    'short' => 'INR',
                ],
                [
                    'name'  => 'IVA Sujeto Exento',
                    'short' => 'ISE',
                ],
                [
                    'name'  => 'Consumidor Final',
                    'short' => 'CF',
                ],
                [
                    'name'  => 'Responsable Monotributo',
                    'short' => 'M',
                ],
                [
                    'name'  => 'Sujeto no Categorizado',
                    'short' => 'SNC',
                ],
                [
                    'name'  => 'Proveedor del Exterior',
                    'short' => 'PE',
                ],
                [
                    'name'  => 'Cliente del Exterior',
                    'short' => 'CE',
                ],
                [
                    'name'  => 'IVA Liberado – Ley Nº 19.640',
                    'short' => 'IL',
                ],
                [
                    'name'  => 'IVA Responsable Inscripto – Agente de Percepción',
                    'short' => 'IRI Perc.',
                ],
                [
                    'name'  => 'Pequeño Contribuyente Eventual',
                    'short' => 'PCE',
                ],
                [
                    'name'  => 'Monotributista Social',
                    'short' => 'MS',
                ],
                [
                    'name'  => 'Pequeño Contribuyente Eventual Social',
                    'short' => 'PCES',
                ],

            ]
        );
    }
}
