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
            $table->id();

            // --- Relaciones y Claves Foráneas ---
            // OJO con los tipos: las tablas legacy (2023) usan increments('id') = INT UNSIGNED,
            // así que sus FK tienen que ser unsignedInteger. Las tablas nuevas (orders,
            // order_items, payments, payment_methods) usan id() = BIGINT UNSIGNED y ahí sí
            // va foreignId. Un tipo que no coincide con la columna referenciada falla con
            // errno 150 (Foreign key constraint is incorrectly formed).
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('customer_id')->nullable();
            $table->unsignedInteger('status_id')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->unsignedInteger('sale_invoice_id')->nullable();
            // Sin default: el default(101) apuntaba a un voucher inexistente y hacía
            // fallar el INSERT (FK 1452). El comprobante se define al facturar.
            $table->unsignedInteger('voucher_id')->nullable();
            $table->foreignId('parent_id')->nullable(); // Relación a sí misma (orders.id es BIGINT)
            $table->unsignedInteger('is_editing_by_user')->nullable();

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

            // --- Claves foráneas (explícitas: el tipo de cada columna lo define la tabla referenciada) ---
            $table->foreign('company_id')->references('id')->on('companies');
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->foreign('status_id')->references('id')->on('statuses');
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('sale_invoice_id')->references('id')->on('sale_invoices');
            $table->foreign('voucher_id')->references('id')->on('vouchers');
            $table->foreign('parent_id')->references('id')->on('orders');
            $table->foreign('is_editing_by_user')->references('id')->on('users');
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
