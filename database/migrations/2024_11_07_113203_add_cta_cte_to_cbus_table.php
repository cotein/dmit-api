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
            $table->string('cta_cte')->nullable()->after('alias');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cbus', function (Blueprint $table) {
            $table->dropColumn('cta_cte');
        });
    }
};
