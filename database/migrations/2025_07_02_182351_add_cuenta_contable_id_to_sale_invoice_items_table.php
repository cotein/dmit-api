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
            // accounting_accounts.id es INT UNSIGNED (tabla legacy con increments):
            // con foreignId (BIGINT) la FK falla con errno 150.
            $table->unsignedInteger('accounting_account_id')->nullable();

            $table->foreign('accounting_account_id')
                  ->references('id')
                  ->on('accounting_accounts')
                  ->onDelete('set null');
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