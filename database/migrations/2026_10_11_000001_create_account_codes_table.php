<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Códigos de un solo uso para cosas de la cuenta (hoy: restablecer la contraseña).
 *
 * El código NUNCA se guarda en claro: va hasheado y se compara con Hash::check.
 * La tabla es la fuente de verdad (y no la cache) por tres razones concretas:
 * se puede auditar quién pidió qué y desde qué IP, se pueden limitar los intentos,
 * y el "un solo uso" queda escrito en la base en vez de depender de que el driver
 * de cache no se vacíe.
 *
 * `purpose` deja la puerta abierta a los códigos de la Fase 2 (segundo factor por
 * mail y "entrar con un código") sin crear una tabla nueva por cada flujo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_codes', function (Blueprint $table) {
            $table->id();

            // users.id es INT UNSIGNED en la base real y el migration legacy usa
            // increments(): BIGINT cubre los dos casos (mismo criterio que
            // mercado_pago_accounts). Sin constraint de FK a propósito: el tipo real
            // de la columna legacy no coincide con el migration y MySQL la rechaza.
            $table->unsignedBigInteger('user_id');
            $table->string('email', 191);
            $table->string('purpose', 32);
            $table->string('code_hash');
            $table->dateTime('expires_at');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->dateTime('used_at')->nullable();
            $table->string('requested_ip', 45)->nullable();
            $table->timestamps();

            // La consulta real es "el último código de este mail para este fin".
            $table->index(['email', 'purpose', 'created_at'], 'account_codes_email_purpose_index');
            $table->index(['user_id', 'purpose'], 'account_codes_user_purpose_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_codes');
    }
};
