<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cuenta de MercadoLibre conectada por cada empresa.
     *
     * Reemplaza a la vieja tabla `meli_tokens` (2023), que quedó sin uso: aquel
     * diseño guardaba el token en texto plano y no contemplaba ni el sitio, ni
     * el estado de la conexión, ni el hecho de que el refresh token es de un
     * solo uso.
     */
    public function up(): void
    {
        Schema::create('mercadolibre_accounts', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('company_id')->unsigned();

            // user_id del vendedor (no confundir con el id de la aplicación).
            $table->unsignedBigInteger('ml_user_id')->nullable();
            $table->string('site_id', 10)->nullable()->default('MLA');
            $table->string('nickname', 191)->nullable();
            $table->string('email', 191)->nullable();

            // Cifrados con APP_KEY (cast 'encrypted') y fuera de cualquier JSON.
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->dateTime('token_expires_at')->nullable();

            $table->string('scopes', 191)->nullable();
            $table->string('status', 20)->default('pending');
            $table->dateTime('connected_at')->nullable();
            $table->dateTime('last_refreshed_at')->nullable();
            $table->dateTime('last_synced_at')->nullable();
            $table->text('last_error')->nullable();

            // Vendedores con el tag `user_product_seller`: publican con el modelo
            // nuevo (User Products) y no aceptan el alta vieja con variaciones.
            $table->boolean('user_product_seller')->default(false);

            $table->json('raw')->nullable();
            $table->timestamps();

            $table->unique('company_id');
            $table->index('status');
            $table->index('ml_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mercadolibre_accounts');
    }
};
