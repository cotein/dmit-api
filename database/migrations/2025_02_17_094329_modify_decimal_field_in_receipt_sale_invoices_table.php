<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('receipt_sale_invoices', function (Blueprint $table) {
            /* $table->decimal('import_payment', 12, 2)->change();
            $table->decimal('import_paid_history', 12, 2)->change(); */
            DB::statement('ALTER TABLE receipt_sale_invoices MODIFY import_payment DECIMAL(12, 2);');
            DB::statement('ALTER TABLE receipt_sale_invoices MODIFY import_paid_history DECIMAL(12, 2);');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('receipt_sale_invoices', function (Blueprint $table) {
            //
        });
    }
};
