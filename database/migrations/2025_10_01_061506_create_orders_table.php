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
        Schema::create('orders', function (Blueprint $table) {
            $table->id(); // Equivalente a int unsigned NOT NULL AUTO_INCREMENT, pero como bigInteger.

            // --- Relaciones y Claves Foráneas ---
            // Novedad: Campo para multi-tenant
            $table->foreignId('company_id')->constrained('companies');

            // Mejora: Uso de foreignId()->constrained() para integridad referencial.
            $table->foreignId('customer_id')->nullable()->constrained('customers');
            $table->foreignId('status_id')->nullable()->constrained('statuses'); // Asumiendo que tienes una tabla 'order_statuses'
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->foreignId('sale_invoice_id')->nullable()->constrained('sale_invoices');
            // Sin default: el default(101) apuntaba a un voucher inexistente y hacía
            // fallar el INSERT (FK 1452). El comprobante se define al facturar.
            $table->foreignId('voucher_id')->nullable()->constrained('vouchers');
            $table->foreignId('parent_id')->nullable()->constrained('orders'); // Relación a sí misma
            $table->foreignId('is_editing_by_user')->nullable()->constrained('users');

            // --- Campos de Negocio ---
            $table->string('code', 100)->nullable();
            $table->unsignedInteger('number')->nullable();
            $table->unsignedInteger('pay_method')->default(1); // Podría ser una FK a 'payment_methods'

            // Mejora: Uso de decimal para valores monetarios en lugar de double para evitar errores de precisión.
            $table->decimal('total', 12, 2)->default(0.00);
            $table->decimal('aditional_pay_method', 10, 2)->default(0.00);

            // --- Campos de Fecha ---
            $table->date('delivery_date')->nullable();
            $table->date('created_on_meli')->nullable();
            // Advertencia: Este campo 'date' como varchar es una mala práctica.
            // Se recomienda refactorizar a un tipo de dato de fecha (date, datetime, timestamp).
            $table->string('date', 100)->nullable();

            // --- Campos de Mercado Libre (Meli) ---
            $table->string('meli_id')->nullable();
            // Mejora: Uso de boolean para tinyint(1)
            $table->boolean('is_meli_order')->default(false);
            $table->json('meli_data')->nullable();

            // --- Campos de Logística y Auditoría ---
            $table->string('delivery_address')->nullable();
            $table->string('who_prepared')->nullable();
            $table->string('who_delivered')->nullable();
            $table->boolean('is_editing')->default(false);

            // --- Campos JSON ---
            $table->json('geocoder')->nullable();

            // --- Timestamps ---
            $table->timestamps(); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
