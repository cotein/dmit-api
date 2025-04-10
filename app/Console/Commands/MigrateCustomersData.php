<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateCustomersData extends Command
{
    protected $signature = 'migrate:customers';
    protected $description = 'Migrate customers data with relationships one by one (from highest to lowest ID)';

    public function handle()
    {
        ini_set('memory_limit', '512M');

        // Desactivar logs de consultas para mejorar rendimiento
        DB::connection('dmit')->disableQueryLog();
        DB::connection('vete')->disableQueryLog();

        // 1. Primero migrar todos los productos
        $this->migrateProducts();

        // 2. Luego migrar clientes con sus relaciones
        $totalCustomers = DB::connection('vete')->table('customers')->count();
        $this->info("Total customers to migrate: {$totalCustomers}");
        $this->newLine();

        $progressBar = $this->output->createProgressBar($totalCustomers);
        $progressBar->start();

        // Procesar clientes uno por uno, de mayor a menor ID
        DB::connection('vete')
            ->table('customers')
            ->orderBy('id', 'desc') // Orden descendente para procesar los más recientes primero
            ->chunk(100, function ($customers) use ($progressBar) {
                foreach ($customers as $customer) {
                    try {
                        // Verificar si el cliente ya existe en la base de datos destino
                        $existingCustomer = DB::connection('dmit')
                            ->table('customers')
                            ->where(function($query) use ($customer) {
                                $query->where('dni', $customer->number ?? $customer->dni ?? null)
                                      ->orWhere('afip_number', $customer->number ?? $customer->afip_number ?? null);
                            })
                            ->first();

                        if ($existingCustomer) {
                            $this->info("\nCustomer ID: {$customer->id} already exists in target database as ID: {$existingCustomer->id} - Skipping...");
                            $progressBar->advance();
                            continue;
                        }

                        DB::connection('dmit')->transaction(function() use ($customer, $progressBar) {
                            // 1. Insertar el cliente
                            $dni = $customer->number ?? $customer->dni ?? null;
                            $afipNumber = $customer->number ?? $customer->afip_number ?? null;

                            $newCustomerId = DB::connection('dmit')->table('customers')->insertGetId([
                                'name' => $customer->name ?? null,
                                'last_name' => null,
                                'fantasy_name' => null,
                                'dni' => $dni,
                                'afip_number' => $afipNumber,
                                'afip_inscription_id' => $customer->inscription_id ?? null,
                                'afip_document_id' => $customer->purchaser_document_id ?? null,
                                'afip_type' => null,
                                'contact' => $customer->contact ?? null,
                                'afip_data' => isset($customer->afip_data) ? json_encode($customer->afip_data) : null,
                                'created_at' => $customer->created_at ?? now(),
                                'updated_at' => $customer->updated_at ?? now(),
                                'cell_phone' => $customer->cell_phone ?? null,
                                'phone_1' => $customer->phone_1 ?? null,
                                'phone_2' => $customer->phone_2 ?? null,
                                'phone_3' => $customer->phone_3 ?? null,
                                'email' => $customer->email ?? null,
                                'obs' => isset($customer->others) ? json_encode($customer->others) : null,
                                'active' => isset($customer->status_id) ? ($customer->status_id == 1 ? 1 : 0) : 1,
                                'meli_id' => $customer->meli_id ?? null,
                                'meli_nick' => $customer->meli_nick ?? null,
                                'pay_condition' => $customer->pay_condition ?? null,
                                'customer_type_id' => $customer->customer_type_id ?? null,
                                'company_id' => 5,
                                'user_id' => 5,
                            ]);

                            $this->info("\nInserted customer ID: {$customer->id} (old) => {$newCustomerId} (new)");

                            // 2. Migrar direcciones relacionadas
                            $this->info("  → Looking for addresses for customer ID: {$customer->id}");
                            $addresses = DB::connection('vete')
                                ->table('addresses')
                                ->where('addressable_id', $customer->id)
                                ->where('addressable_type', 'App\Src\Models\Customer')
                                ->get();

                            $this->info("  → Found ".count($addresses)." addresses to migrate");

                            $addressIds = [];
                            foreach ($addresses as $address) {
                                $this->info("    → Processing address ID: {$address->id}");

                                $addressData = [
                                    'code' => $address->code,
                                    'country_id' => $address->country_id,
                                    'state_id' => $address->province_id,
                                    'city' => $address->city,
                                    'street' => $address->address,
                                    'number' => (int)$address->number,
                                    'cp' => $address->cp,
                                    'obs' => $address->obs,
                                    'geocoder' => json_encode($address->geocoder),
                                    'addressable_id' => $newCustomerId,
                                    'addressable_type' => 'App\Models\Customer',
                                    'type_id' => $address->type_id,
                                    'active' => $address->status_id == 1 ? 1 : 0,
                                    'between_streets' => $address->between_streets,
                                    'created_at' => $address->created_at,
                                    'updated_at' => $address->updated_at,
                                ];

                                if (!Schema::connection('dmit')->hasTable('addresses')) {
                                    $this->error("    → ERROR: Table 'addresses' doesn't exist in target database!");
                                    continue;
                                }

                                $newAddressId = DB::connection('dmit')->table('addresses')->insertGetId($addressData);
                                $addressIds[] = $newAddressId;
                                $this->info("    → Inserted address ID: {$newAddressId}");
                            }

                            if (!empty($addressIds)) {
                                $this->info("  → Successfully inserted addresses IDs: " . implode(', ', $addressIds));
                            } else {
                                $this->info("  → No addresses were inserted for this customer");
                            }

                            // 3. Migrar facturas relacionadas
                            $this->info("  → Looking for sales invoices for customer ID: {$customer->id}");
                            $invoices = DB::connection('vete')
                                ->table('sales_invoices')
                                ->where('customer_id', $customer->id)
                                ->get();

                            $this->info("  → Found ".count($invoices)." invoices to migrate");

                            $invoiceIds = [];
                            foreach ($invoices as $invoice) {
                                $this->info("    → Processing invoice ID: {$invoice->id}");

                                // Procesar fechas que podrían ser null
                                $cbteFch = isset($invoice->cbte_fch) ? $invoice->cbte_fch : null;
                                $caeFchVto = isset($invoice->cae_fch_vto) ? $invoice->cae_fch_vto : null;
                                $vtoPayment = isset($invoice->vto_payment) ? $invoice->vto_payment : null;

                                $invoiceData = [
                                    'company_id' => 5,
                                    'customer_id' => $newCustomerId,
                                    'voucher_id' => $invoice->voucher_id,
                                    'pto_vta' => $invoice->pto_vta,
                                    'cbte_desde' => $invoice->cbte_desde,
                                    'cbte_hasta' => $invoice->cbte_hasta,
                                    'cbte_fch' => $cbteFch,
                                    'cae' => $invoice->cae,
                                    'cae_fch_vto' => $caeFchVto,
                                    'user_id' => $invoice->user_id,
                                    'afip_data' => json_encode($invoice->afip_data),
                                    'created_at' => $invoice->created_at,
                                    'updated_at' => $invoice->updated_at,
                                    'vto_payment' => $vtoPayment,
                                    'commercial_reference' => $invoice->commercial_reference,
                                    'payment_type_id' => $invoice->payment_type_id,
                                    'sales_condition_id' => 1,
                                    'status_id' => $invoice->status_id,
                                    'fch_serv_desde' => null,
                                    'fch_serv_hasta' => null,
                                    'parent_id' => $invoice->parent_id,
                                    'fch_vto_pago' => null,
                                ];

                                if (!Schema::connection('dmit')->hasTable('sale_invoices')) {
                                    $this->error("    → ERROR: Table 'sale_invoices' doesn't exist in target database!");
                                    continue;
                                }

                                $newInvoiceId = DB::connection('dmit')->table('sale_invoices')->insertGetId($invoiceData);
                                $invoiceIds[] = $newInvoiceId;
                                $this->info("    → Inserted invoice ID: {$newInvoiceId}");

                                // 4. Migrar items de factura
                                $this->info("    → Looking for invoice items for invoice ID: {$invoice->id}");
                                $items = DB::connection('vete')
                                    ->table('sale_invoice_items')
                                    ->where('sale_invoice_id', $invoice->id)
                                    ->get();

                                $this->info("    → Found ".count($items)." items to migrate");

                                $itemIds = [];
                                foreach ($items as $item) {
                                    $this->info("      → Processing item ID: {$item->id}");

                                    // Obtener el voucher_id de la factura padre
                                    $voucherId = $invoice->voucher_id ?? null;

                                    $itemData = [
                                        'sale_invoice_id' => $newInvoiceId,
                                        'product_id' => $item->product_id,
                                        'quantity' => $item->quantity,
                                        'neto_import' => $item->neto_import,
                                        'iva_import' => $item->iva_import,
                                        'iva_id' => 3, // 21%
                                        'discount_percentage' => $item->discount,
                                        'discount_import' => $item->discount_import,
                                        'total' => $item->total,
                                        'obs' => $item->obs,
                                        'created_at' => $item->created_at,
                                        'updated_at' => $item->updated_at,
                                        'unit_price' => $item->unit_price,
                                        'price_list_id' => 5,
                                        'voucher_id' => $voucherId,
                                        'aditional_percentage' => 0.00,
                                        'aditional_value' => 0.00,
                                        'percep_iibb_alicuota' => 0.00,
                                        'percep_iibb_import' => 0.00,
                                        'percep_iva_alicuota' => 0.00,
                                        'percep_iva_import' => 0.00,
                                    ];

                                    if (!Schema::connection('dmit')->hasTable('sale_invoice_items')) {
                                        $this->error("      → ERROR: Table 'sale_invoice_items' doesn't exist in target database!");
                                        continue;
                                    }

                                    $newItemId = DB::connection('dmit')->table('sale_invoice_items')->insertGetId($itemData);
                                    $itemIds[] = $newItemId;
                                    $this->info("      → Inserted item ID: {$newItemId}");
                                }

                                if (!empty($itemIds)) {
                                    $this->info("    → Successfully inserted items IDs: " . implode(', ', $itemIds));
                                } else {
                                    $this->info("    → No items were inserted for this invoice");
                                }
                            }

                            if (!empty($invoiceIds)) {
                                $this->info("  → Successfully inserted invoices IDs: " . implode(', ', $invoiceIds));
                            } else {
                                $this->info("  → No invoices were inserted for this customer");
                            }
                        });

                        $progressBar->advance();
                    } catch (\Exception $e) {
                        $this->error("\nError migrating customer ID: {$customer->id}");
                        $this->error($e->getMessage());
                        $this->error("Stack trace: ".$e->getTraceAsString());
                        continue;
                    }
                }
            });

        $progressBar->finish();
        $this->newLine(2);
        $this->info('Customers migration completed successfully.');
    }

    protected function migrateProducts()
    {
        $this->info("Starting products migration...");
        $totalProducts = DB::connection('vete')->table('products')->count();
        $this->info("Total products to migrate: {$totalProducts}");
        $this->newLine();

        $progressBar = $this->output->createProgressBar($totalProducts);
        $progressBar->start();

        DB::connection('vete')
            ->table('products')
            ->orderBy('id', 'desc') // También ordenamos productos de mayor a menor ID
            ->chunk(100, function ($products) use ($progressBar) {
                foreach ($products as $product) {
                    try {
                        // Verificar si el producto ya existe en la base de datos destino
                        $existingProduct = DB::connection('dmit')
                            ->table('products')
                            ->where('code', $product->code)
                            ->first();

                        if ($existingProduct) {
                            $this->info("Product with code {$product->code} already exists in target database - Skipping...");
                            $progressBar->advance();
                            continue;
                        }

                        DB::connection('dmit')->transaction(function() use ($product, $progressBar) {
                            $productData = [
                                'meli_id' => $product->meli_id,
                                'company_id' => 5,
                                'name' => $product->name,
                                'code' => $product->code,
                                'sub_title' => $product->sub_title,
                                'description' => $product->description,
                                'iva_id' => 3, //21% de IVA
                                'money_id' => 1, // Extraer money_id del JSON
                                'priority_id' => $product->priority_id,
                                'published_meli' => $product->published_meli,
                                'published_here' => $product->published_here,
                                'active' => $product->active,
                                'slug' => $product->slug,
                                'deleted_at' => $product->deleted_at,
                                'created_at' => $product->created_at,
                                'updated_at' => $product->updated_at,
                                'critical_stock' => $product->critical_stock,
                                'sale_by_meters' => $product->mts_by_unity ? 1 : 0,
                                'mts_by_unity' => $product->mts_by_unity,
                                'apply_discount' => $product->discount_percentage > 0 ? 1 : 0,
                                'apply_discount_amount' => 0.00,
                                'apply_discount_percentage' => $product->discount_percentage,
                                'see_price_on_the_web' => 0,
                            ];

                            if (!Schema::connection('dmit')->hasTable('products')) {
                                $this->error("ERROR: Table 'products' doesn't exist in target database!");
                                return;
                            }

                            $newProductId = DB::connection('dmit')->table('products')->insertGetId($productData);
                            $this->info("Inserted product ID: {$product->id} (old) => {$newProductId} (new)");
                            $progressBar->advance();
                        });
                    } catch (\Exception $e) {
                        $this->error("\nError migrating product ID: {$product->id}");
                        $this->error($e->getMessage());
                        continue;
                    }
                }
            });

        $progressBar->finish();
        $this->newLine(2);
        $this->info('Products migration completed successfully.');
        $this->newLine();
    }
}
