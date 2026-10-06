<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La columna customers.phone se había agregado editando la migración de 2023 ya
 * aplicada: sobre la base existente eso no hace nada (queda registrada como corrida).
 * Va como migración nueva, y con guarda porque las bases creadas a mano ya la tienen.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('customers', 'phone')) {
            return;
        }

        Schema::table('customers', function (Blueprint $table) {
            $table->string('phone', 191)->nullable()->after('cell_phone');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('customers', 'phone')) {
            return;
        }

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('phone');
        });
    }
};
