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
            // Agrega la nueva columna 'accounting_account_id'
            // Será un entero sin signo porque es un ID y no puede ser negativo
            // Puede ser nulo (nullable) al principio, por si ya tienes datos existentes
            // y no quieres que falle la migración. Después puedes rellenarlo.
            $table->unsignedBigInteger('accounting_account_id')->nullable();

            // Opcional: Define una clave foránea para enlazar con tu tabla de cuentas contables
            // Asumiendo que tu tabla de cuentas contables se llama 'cuentas_contables'
            // y que su columna de ID se llama 'id'.
            // Esta línea asegura que solo puedas asignar IDs de cuentas contables que existan.
            $table->foreign('accounting_account_id')
                  ->references('id')
                  ->on('accounting_accounts')
                  ->constrained('accounting_accounts')
                  ->onDelete('set null'); // Si se borra una cuenta contable, este campo se vuelve nulo
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