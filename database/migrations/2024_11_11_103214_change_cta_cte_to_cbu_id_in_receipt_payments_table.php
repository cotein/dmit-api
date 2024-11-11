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
        Schema::table('receipt_payments', function (Blueprint $table) {
            $table->dropColumn('cta_cte');
            $table->integer('cbu_id')->after('payment_type_id'); // Reemplaza 'payment_type_id' con la columna después de la cual deseas agregar 'cbu_id'
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('receipt_payments', function (Blueprint $table) {
            $table->dropColumn('cbu_id');
            $table->string('cta_cte')->nullable()->after('payment_type_id'); // Reemplaza 'payment_type_id' con la columna después de la cual deseas agregar 'cta_cte'
        });
    }
};
