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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            // --- Campos Multi-Tenant y Relaciones ---
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_method_id')->constrained();

            // --- Campos Financieros y de Datos ---
            // Se usa decimal para evitar errores de precisión con valores monetarios.
            $table->decimal('amount', 12, 2);
            $table->date('payment_date');
            $table->string('status')->default('completed'); // Ej: pending, completed, failed
            $table->string('reference_number')->nullable();
            $table->text('notes')->nullable();

            // --- Timestamps ---
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
