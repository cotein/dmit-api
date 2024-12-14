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
        Schema::table('receipt_sale_invoices', function (Blueprint $table) {

            // Eliminar columnas existentes
            $table->dropColumn('percentage');
            $table->dropColumn('import');

            // Agregar nuevas columnas
            $table->decimal('percentage_payment')->default(0);
            $table->decimal('import_payment',)->default(0);
            $table->decimal('percentage_paid_history')->default(0);
            $table->decimal('import_paid_history',)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('receipt_sale_invoices', function (Blueprint $table) {
            $table->decimal('percentage');
            $table->decimal('import');

            $table->dropColumn('percentage_payment');
            $table->dropColumn('import_payment',);
            $table->dropColumn('percentage_paid_history');
            $table->dropColumn('import_paid_history',);
        });
    }
};
