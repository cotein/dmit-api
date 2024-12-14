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
            if (!Schema::hasColumn('sale_invoice_items', 'percep_iibb_alicuota')) {
                $table->decimal('percep_iibb_alicuota', 15, 2)->default(0);
            }
            if (!Schema::hasColumn('sale_invoice_items', 'percep_iibb_import')) {
                $table->decimal('percep_iibb_import', 15, 2)->default(0);
            }
            if (!Schema::hasColumn('sale_invoice_items', 'percep_iva_alicuota')) {
                $table->decimal('percep_iva_alicuota', 15, 2)->default(0);
            }
            if (!Schema::hasColumn('sale_invoice_items', 'percep_iva_import')) {
                $table->decimal('percep_iva_import', 15, 2)->default(0);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_invoice_items', function (Blueprint $table) {
            $table->dropColumn('percep_iibb_alicuota');
            $table->dropColumn('percep_iibb_import');
            $table->dropColumn('percep_iva_alicuota');
            $table->dropColumn('percep_iva_import');
        });
    }
};
