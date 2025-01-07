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
        Schema::table('cbus', function (Blueprint $table) {
            $table->integer('company_id')->nullable()->change();
            $table->integer('bank_id')->nullable()->change();
            $table->string('cbu')->nullable()->change();
            $table->string('alias')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cbus', function (Blueprint $table) {
            $table->integer('company_id')->nullable(false)->change();
            $table->integer('bank_id')->nullable(false)->change();
            $table->string('cbu')->nullable(false)->change();
            $table->string('alias')->nullable(false)->change();
        });
    }
};
