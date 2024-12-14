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
        Schema::create('customer_cuenta_corrientes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('number')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('cuotaable_id');
            $table->string('cuotaable_type');
            $table->decimal('sale', 14, 2)->default(0);
            $table->decimal('pay', 14, 2)->default(0);
            $table->timestamps();

            $table->index('company_id', 'cc_company_id_idx');
            $table->index('customer_id', 'cc_customer_id_idx');
            $table->index(['cuotaable_id', 'cuotaable_type'], 'cc_cuotaable_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_cuenta_corrientes');
    }
};
