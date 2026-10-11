<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doble factor de la cuenta (opcional, decisión del dueño del sistema).
 *
 * Dos métodos que el usuario elige:
 *  - 'totp': app autenticadora (Google Authenticator, Authy, 1Password…). El secreto
 *    se guarda cifrado con el cast 'encrypted' (usa APP_KEY) y nunca sale en un JSON;
 *  - 'email': código de 6 dígitos al correo, reusando la tabla account_codes.
 *
 * `two_factor_confirmed_at` es la marca de "quedó activado": mientras esté en null,
 * hay un secreto pendiente de confirmar (así nadie se queda afuera por escanear un
 * QR y no confirmar).
 *
 * Los códigos de recuperación van hasheados en una columna de texto (JSON): se
 * muestran una sola vez y cada uno se consume al usarse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable();
            $table->string('two_factor_method', 16)->nullable();
            $table->dateTime('two_factor_confirmed_at')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'two_factor_secret',
                'two_factor_method',
                'two_factor_confirmed_at',
                'two_factor_recovery_codes',
            ]);
        });
    }
};
