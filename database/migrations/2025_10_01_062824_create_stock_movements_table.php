<?php

use App\Src\Enums\StockMovementType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->integer('quantity'); // Puede ser positivo o negativo
            $table->integer('stock_after_change');
            $table->string('type')->default(StockMovementType::MANUAL_ADJUSTMENT->value);
            $table->morphs('sourceable'); // Esto crea sourceable_id y sourceable_type
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};