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
        Schema::create('price_list_product', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pricelist_id');
            $table->unsignedBigInteger('product_id');
            $table->float('price', 12, 2);
            $table->float('profit_percentage', 12, 2);
            $table->float('profit_rate', 12, 2);
            $table->timestamps();

            $table->index(['product_id', 'pricelist_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_list_product');
    }
};
