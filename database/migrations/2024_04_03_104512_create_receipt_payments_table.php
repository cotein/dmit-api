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
        Schema::create('receipt_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('receipt_id')->nullable();
            $table->unsignedBigInteger('payment_type_id')->nullable();
            $table->string('number')->nullable();
            $table->string('cta_cte')->nullable();
            $table->date('date');
            $table->string('description')->nullable();
            $table->decimal('total', 14, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receipt_payments');
    }
};
