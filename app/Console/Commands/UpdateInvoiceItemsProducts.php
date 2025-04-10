<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateInvoiceItemsProducts extends Command
{
    protected $signature = 'update:invoice-items-products';
    protected $description = 'Update product_id in sale_invoice_items for invoices with ID > 53204';

    // Mapeo de IDs viejos a nuevos
    protected $productIdMapping = [
        1 => 33,
        2 => 34,
        3 => 35,
        4 => 36,
        5 => 37,
        6 => 38,
        7 => 39,
        8 => 40,
        9 => 41,
        10 => 42,
        11 => 43,
        12 => 44,
        13 => 45,
        14 => 46,
        15 => 47,
        16 => 48
    ];

    public function handle()
    {
        ini_set('memory_limit', '512M');

        $this->info('Starting update of product_id in sale_invoice_items...');

        // Contar registros a procesar
        $totalItems = DB::connection('dmit')
            ->table('sale_invoice_items')
            ->where('sale_invoice_id', '>', 53204)
            ->whereIn('product_id', array_keys($this->productIdMapping))
            ->count();

        $this->info("Total items to update: {$totalItems}");

        if ($totalItems === 0) {
            $this->info('No items found to update.');
            return;
        }

        $progressBar = $this->output->createProgressBar($totalItems);
        $progressBar->start();

        try {
            // Procesar por lotes para mejor rendimiento
            DB::connection('dmit')
                ->table('sale_invoice_items')
                ->where('sale_invoice_id', '>', 53204)
                ->whereIn('product_id', array_keys($this->productIdMapping))
                ->orderBy('id')
                ->chunk(200, function ($items) use ($progressBar) {
                    foreach ($items as $item) {
                        $newProductId = $this->productIdMapping[$item->product_id] ?? $item->product_id;

                        DB::connection('dmit')
                            ->table('sale_invoice_items')
                            ->where('id', $item->id)
                            ->update(['product_id' => $newProductId]);

                        $progressBar->advance();
                    }
                });

            $progressBar->finish();
            $this->newLine(2);
            $this->info('Update completed successfully!');
        } catch (\Exception $e) {
            $this->error('Error during update:');
            $this->error($e->getMessage());
        }
    }
}
