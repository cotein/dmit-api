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
            $table->decimal('percentage', 5, 2)->after('receipt_id'); // Campo percentage
            $table->decimal('import', 15, 2)->after('percentage'); // Campo importe

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('receipt_sale_invoices', function (Blueprint $table) {
            $table->dropColumn('percentage');
            $table->dropColumn('import');
        });
    }
};
