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
        Schema::table('products', function (Blueprint $table) {
            // accounting_accounts.id es INT UNSIGNED (tabla legacy con increments)
            $table->unsignedInteger('accounting_account_id')->nullable();

            // FK explícita: $table->foreign() (el ->constrained() sobre una columna
            // común no crea el constraint, Fluent lo ignora en silencio).
            $table->foreign('accounting_account_id')
                  ->references('id')
                  ->on('accounting_accounts')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Elimina la clave foránea si existe, antes de eliminar la columna
            $table->dropForeign(['accounting_account_id']);

            // Elimina la columna 'accounting_account_id'
            $table->dropColumn('accounting_account_id');
        });
    }
};