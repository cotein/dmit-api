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
            $table->unsignedInteger('bank_id')->nullable();
            $table->date('cheque_date')->nullable();
            $table->date('cheque_expirate')->nullable();
            $table->string('cheque_owner')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('receipt_payments', function (Blueprint $table) {
            $table->dropColumn('bank_id');
            $table->dropColumn('cheque_date');
            $table->dropColumn('cheque_expirate');
            $table->dropColumn('cheque_owner');
        });
    }
};
