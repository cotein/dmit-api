<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cuenta de Mercado Pago conectada por cada empresa (modelo marketplace).
 *
 * Cada comercio conecta SU cuenta vía OAuth; el access_token (cifrado con el
 * cast 'encrypted') es el que firma las preferencias y los QR de esa empresa,
 * así la plata entra directo al comercio y nunca a una cuenta de DMIT.
 *
 * OJO con el tipo: el migration legacy de `companies` usa increments(), pero la
 * columna REAL en la base es BIGINT UNSIGNED (verificado con SHOW COLUMNS). Con
 * unsignedInteger() la FK falla con MySQL 3780 "incompatible columns" y deja la
 * tabla creada pero sin registrar. Por eso acá va foreignId().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mercado_pago_accounts', function (Blueprint $table) {
            $table->id();

            // companies.id es BIGINT UNSIGNED en la base real y INT UNSIGNED en los
            // migrations: BIGINT cubre los dos casos (ver nota del final).
            $table->unsignedBigInteger('company_id');

            // Identidad del usuario de Mercado Pago que autorizó la conexión.
            $table->string('mp_user_id')->nullable();
            $table->string('nickname')->nullable();
            $table->string('email')->nullable();
            $table->string('public_key', 512)->nullable();

            // Credenciales OAuth. Se guardan cifradas y nunca se serializan.
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->string('scopes')->nullable();

            // pending | connected | revoked | error
            $table->string('status')->default('pending');
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_refreshed_at')->nullable();
            $table->text('last_error')->nullable();

            $table->timestamps();

            // Una cuenta activa por empresa: reconectar reemplaza la anterior.
            $table->unique('company_id');
            $table->index('mp_user_id');

            /*
             * SIN foreign key a companies, a propósito.
             *
             * companies.id es INT UNSIGNED en los migrations (increments) pero
             * BIGINT UNSIGNED en la base real: cualquier FK a esa tabla funciona
             * en una y rompe en la otra con MySQL 3780 "incompatible columns".
             * Con FK el módulo no se podía instalar en una base nueva (deploy
             * limpio o base de test), sin FK anda en las dos. La columna es
             * BIGINT UNSIGNED, que acepta ids de origen INT o BIGINT.
             */
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mercado_pago_accounts');
    }
};
