<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Espejo local de las publicaciones (items) de MercadoLibre.
     *
     * `product_id` es el vínculo con nuestro catálogo y `match_status` cuenta si
     * se vinculó solo por SKU, si quedó pendiente de conciliar o si el usuario
     * decidió ignorarla. El JSON crudo se guarda para no tener que volver a
     * pedir el item cuando aparece un dato que no habíamos mapeado.
     */
    public function up(): void
    {
        Schema::create('mercadolibre_items', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('company_id')->unsigned();
            $table->unsignedInteger('mercadolibre_account_id');

            // id de la publicación en MercadoLibre (ej: MLA1234567890).
            $table->string('item_id', 191);
            $table->string('site_id', 10)->nullable();
            $table->string('title', 191)->nullable();

            $table->string('category_id', 191)->nullable();
            $table->string('domain_id', 191)->nullable();
            $table->string('listing_type_id', 60)->nullable();
            $table->string('condition', 30)->nullable();
            $table->string('currency_id', 10)->nullable();

            $table->decimal('price', 14, 2)->nullable();
            $table->decimal('base_price', 14, 2)->nullable();
            $table->integer('available_quantity')->nullable();
            $table->integer('sold_quantity')->nullable();

            // active / paused / closed / under_review ...
            $table->string('status', 30)->nullable();
            // out_of_stock / paused_by_seller / warning / held ...
            $table->string('sub_status', 60)->nullable();

            $table->string('permalink', 255)->nullable();
            $table->text('thumbnail')->nullable();

            $table->boolean('catalog_listing')->default(false);
            $table->string('catalog_product_id', 191)->nullable();

            // Modelo nuevo (User Products): familia y producto del vendedor.
            $table->string('family_name', 191)->nullable();
            $table->string('user_product_id', 191)->nullable();

            $table->string('health', 30)->nullable();
            $table->json('tags')->nullable();

            // Vínculo con nuestro producto. Nullable a propósito: una publicación
            // sin producto local es un caso normal (hay que conciliarla).
            $table->unsignedInteger('product_id')->nullable();
            // matched | unmatched | ignored
            $table->string('match_status', 20)->default('unmatched');
            $table->string('matched_by', 20)->nullable();
            $table->dateTime('matched_at')->nullable();

            // `last_updated` de MercadoLibre: permite reconciliar sin bajar todo.
            $table->dateTime('ml_last_updated')->nullable();
            $table->dateTime('synced_at')->nullable();

            // Estado del último intento de escritura hacia MercadoLibre.
            $table->dateTime('last_pushed_at')->nullable();
            $table->text('last_push_error')->nullable();

            $table->json('raw')->nullable();
            $table->timestamps();

            $table->unique('item_id');
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'match_status']);
            $table->index('product_id');
            $table->index('ml_last_updated');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mercadolibre_items');
    }
};
