<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Variaciones de una publicación (talle, color, etc.).
     *
     * Van en tabla aparte y no en una columna JSON porque cada variación tiene
     * su propio stock y su propio SKU: son las dos cosas que necesitamos
     * consultar y actualizar. El stock de la publicación en MercadoLibre es la
     * suma de sus variaciones.
     */
    public function up(): void
    {
        Schema::create('mercadolibre_variations', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('company_id')->unsigned();
            $table->unsignedInteger('mercadolibre_item_id');

            // id de la variación dentro de la publicación.
            $table->string('variation_id', 191);

            $table->decimal('price', 14, 2)->nullable();
            $table->integer('available_quantity')->nullable();
            $table->integer('sold_quantity')->nullable();
            $table->string('seller_sku', 191)->nullable();
            $table->string('catalog_product_id', 191)->nullable();

            // [{id: 'COLOR', value_id, value_name}, ...] — se guardan crudos
            // porque el nombre del atributo viene en el idioma del sitio.
            $table->json('attribute_combinations')->nullable();
            $table->json('attributes')->nullable();
            $table->json('picture_ids')->nullable();

            $table->dateTime('ml_last_updated')->nullable();
            $table->timestamps();

            $table->unique(['mercadolibre_item_id', 'variation_id']);
            $table->index('seller_sku');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mercadolibre_variations');
    }
};
