<?php

namespace App\Console\Commands;

use App\Models\SaleInvoices;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateSaleInvoiceItemsData extends Command
{
    protected $signature = 'migrate:sale-invoice-items';
    protected $description = 'Migrate sale invoice items data from old database to new database using chunks';

    public function handle()
    {
        // Aumentar el límite de memoria
        ini_set('memory_limit', '256M');

        // Definir el tamaño del chunk
        $chunkSize = 500;

        // Obtener el total de registros para mostrar el progreso
        $totalRecords = DB::connection('vete')->table('sale_invoice_items')->count();
        $this->info("Total records to migrate: {$totalRecords}");

        // Procesar los registros en chunks
        DB::connection('vete')
            ->table('sale_invoice_items')
            ->orderBy('id') // Ordenar por ID para evitar problemas con chunks
            ->chunk($chunkSize, function ($invoiceItems) {
                $this->info("Processing a chunk of " . count($invoiceItems) . " records...");

                $dataToInsert = [];

                foreach ($invoiceItems as $item) {
                    $voucherId = DB::connection('vete')
                        ->table('sales_invoices')
                        ->where('id', $item->sale_invoice_id)
                        ->value('voucher_id');
                    // Mapear los campos de la base de datos antigua a la nueva
                    $siId = $item->sale_invoice_id + 24589; // Sumar la cantidad que corresponde
                    $dataToInsert[] = [
                        'sale_invoice_id' => $siId, //sumar la cantidad que corresponde
                        'product_id' => $item->product_id + 16,
                        'quantity' => $item->quantity,
                        'neto_import' => $item->neto_import,
                        'iva_import' => $item->iva_import,
                        'iva_id' => 3, // 21%
                        'discount_percentage' => $item->discount, // Cambio de nombre
                        'discount_import' => $item->discount_import,
                        'total' => $item->total,
                        'obs' => $item->obs,
                        'created_at' => $item->created_at,
                        'updated_at' => $item->updated_at,
                        'unit_price' => $item->unit_price,
                        'price_list_id' => 5, // No existe en la tabla vieja
                        'voucher_id' => $voucherId, // No existe en la tabla vieja
                        'aditional_percentage' => 0.00, // Valor por defecto
                        'aditional_value' => 0.00, // Valor por defecto
                        'percep_iibb_alicuota' => 0.00, // Valor por defecto
                        'percep_iibb_import' => 0.00, // Valor por defecto
                        'percep_iva_alicuota' => 0.00, // Valor por defecto
                        'percep_iva_import' => 0.00, // Valor por defecto
                    ];
                }

                // Insertar el chunk en la nueva base de datos
                DB::connection('dmit')->table('sale_invoice_items')->insert($dataToInsert);

                // Liberar memoria
                unset($dataToInsert);
            });

        $this->info('Sale invoice items data migrated successfully.');
    }
}
