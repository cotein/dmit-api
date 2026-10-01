<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();

            // --- Relaciones ---
            // Se asume que la tabla de pedidos se llama 'orders' como en el ejemplo anterior.
            // onDelete('cascade') borra los items si se borra el pedido. Esencial.
            $table->foreignId('order_id')->nullable()->constrained('orders')->onDelete('cascade');
            $table->foreignId('product_id')->nullable()->constrained('products');
            $table->foreignId('pricelist_id')->nullable()->constrained('price_lists'); // Asumiendo tabla 'price_lists'
            $table->foreignId('iva_id')->nullable()->constrained('ivas'); // Asumiendo tabla 'ivas'

            // --- Cálculos y Precios ---
            // Mejora: Se utiliza decimal() en lugar de double() para todos los cálculos y precios.
            $table->decimal('unit_price', 15, 2)->default(0.00);

            // Nota: La cantidad como decimal es inusual, pero se respeta la estructura original.
            // Si tus productos siempre se venden en unidades enteras, considera cambiarlo a ->unsignedInteger('quantity').
            $table->decimal('quantity', 15, 2)->default(1.00);

            $table->unsignedInteger('discount_percentage')->default(0);
            $table->decimal('discount_import', 15, 2)->default(0.00);
            $table->decimal('iva_percentage', 8, 2)->default(0.00);
            $table->decimal('iva_import', 15, 2)->default(0.00);
            $table->decimal('neto_import', 12, 2)->default(0.00);
            $table->decimal('total', 12, 2)->default(0.00);

            // --- Medidas (MTS) ---
            $table->decimal('mts', 8, 2)->nullable();
            $table->decimal('rounded_mts', 8, 2)->nullable();
            $table->decimal('real_mts', 12, 2)->nullable();
            $table->decimal('mts_to_invoiced', 12, 2)->nullable();

            // --- Otros Campos ---
            $table->json('price_list')->nullable();
            $table->boolean('is_chp')->nullable(); // Mejora: boolean() para tinyint(1)

            $table->timestamps(); // Maneja created_at y updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
