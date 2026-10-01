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
        Schema::table('sale_invoice_items', function (Blueprint $table) {
            // Reemplaza tu definición actual de la columna con esta línea
            $table->foreignId('accounting_account_id')
                  ->nullable() // Si la relación es opcional
                  ->constrained('accounting_accounts') // Crea el constraint
                  ->onDelete('set null'); // Define el comportamiento al borrar
        });
    }

    public function down(): void
    {
        Schema::table('sale_invoice_items', function (Blueprint $table) {
            // Importante: el down debe revertir el up correctamente
            $table->dropForeign(['accounting_account_id']);
            $table->dropColumn('accounting_account_id');
        });
    }
};