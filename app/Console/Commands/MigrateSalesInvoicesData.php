<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MigrateSalesInvoicesData extends Command
{
    protected $signature = 'migrate:sales-invoices';
    protected $description = 'Migrate sales invoices data from old database to new database using chunks';

    public function handle()
    {
        ini_set('memory_limit', '512M'); // Aumenta el límite de memoria a 256 MB
        // Definir el tamaño del chunk
        $chunkSize = 500;

        // Obtener el total de registros para mostrar el progreso
        $totalRecords = DB::connection('vete')->table('sales_invoices')->count();
        $this->info("Total records to migrate: {$totalRecords}");

        // Procesar los registros en chunks
        DB::connection('vete')
            ->table('sales_invoices')
            ->orderBy('id') // Ordenar por ID para evitar problemas con chunks
            ->chunk($chunkSize, function ($salesInvoices) {
                $this->info("Processing a chunk of " . count($salesInvoices) . " records...");

                $dataToInsert = [];

                foreach ($salesInvoices as $invoice) {
                    // Convertir fechas de texto a tipo date
                    $cbteFch = $invoice->cbte_fch ? Carbon::createFromFormat('Ymd', $invoice->cbte_fch)->toDateString() : null;
                    $caeFchVto = $invoice->cae_fch_vto ? Carbon::createFromFormat('Ymd', $invoice->cae_fch_vto)->toDateString() : null;
                    $vtoPayment = $invoice->vto_payment ? Carbon::createFromFormat('Ymd', $invoice->vto_payment)->toDateString() : null;

                    // Mapear los campos de la base de datos antigua a la nueva
                    $dataToInsert[] = [
                        'company_id' => 5,
                        'customer_id' => $invoice->customer_id + 21,
                        'voucher_id' => $invoice->voucher_id,
                        'pto_vta' => $invoice->pto_vta,
                        'cbte_desde' => $invoice->cbte_desde,
                        'cbte_hasta' => $invoice->cbte_hasta,
                        'cbte_fch' => $cbteFch,
                        'cae' => $invoice->cae,
                        'cae_fch_vto' => $caeFchVto,
                        'user_id' => $invoice->user_id,
                        'afip_data' => json_encode($invoice->afip_data), // Convertir JSON a texto
                        'created_at' => $invoice->created_at,
                        'updated_at' => $invoice->updated_at,
                        'vto_payment' => $vtoPayment,
                        'commercial_reference' => $invoice->commercial_reference,
                        'payment_type_id' => $invoice->payment_type_id,
                        'sales_condition_id' => null, // No existe en la tabla vieja
                        'status_id' => $invoice->status_id,
                        'fch_serv_desde' => null, // No existe en la tabla vieja
                        'fch_serv_hasta' => null, // No existe en la tabla vieja
                        'parent_id' => $invoice->parent_id,
                        'fch_vto_pago' => null, // No existe en la tabla vieja
                    ];
                }

                // Insertar el chunk en la nueva base de datos
                DB::connection('dmit')->table('sale_invoices')->insert($dataToInsert);
                unset($dataToInsert);
            });

        $this->info('Sales invoices data migrated successfully.');
    }
}
