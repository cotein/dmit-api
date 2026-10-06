<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * users.remember_token se había agregado editando la migración de 2023 ya aplicada
 * (el UserFactory lo necesita). Va como migración nueva para las bases existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'remember_token')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->rememberToken();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'remember_token')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('remember_token');
        });
    }
};
