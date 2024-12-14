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
        Schema::create('general_journals', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->date('date');
            $table->string('description')->nullable();
            $table->integer('debited_account_id')->nullable();
            $table->integer('credited_account_id')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('coin', 3)->default('PES');
            $table->string('transaction_type')->nullable();
            $table->string('referencia')->nullable();
            $table->integer('company_id');
            $table->integer('user_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('general_journals');
    }
};
