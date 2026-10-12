<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Notificaciones (webhooks) recibidas de MercadoLibre.
     *
     * Se guardan ANTES de procesarlas por dos motivos:
     *   - MercadoLibre espera un 200 en menos de 500 ms; si tardamos, reintenta
     *     hasta 8 veces en una hora y después descarta la notificación (y puede
     *     desactivarnos el tópico),
     *   - si el proceso falla, el evento queda pendiente y lo levanta la
     *     conciliación en la próxima corrida.
     *
     * El payload NO se cree: es sólo el aviso. El dato se pide después a la API
     * con el token de la empresa, así una notificación falsa no ensucia nada.
     */
    public function up(): void
    {
        Schema::create('mercadolibre_webhook_events', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('company_id')->unsigned()->nullable();
            $table->unsignedInteger('mercadolibre_account_id')->nullable();

            // _id de la notificación: MercadoLibre puede repetir el aviso.
            $table->string('notification_id', 191)->nullable();
            $table->string('topic', 60)->nullable();
            $table->unsignedBigInteger('ml_user_id')->nullable();
            $table->string('resource', 255)->nullable();
            $table->string('site_id', 10)->nullable();
            $table->unsignedInteger('attempts')->nullable();
            $table->dateTime('sent_at')->nullable();

            // pending | processed | ignored | failed
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('processed_at_ms')->nullable();
            $table->text('error')->nullable();

            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique('notification_id');
            $table->index(['status', 'created_at']);
            $table->index(['company_id', 'topic']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mercadolibre_webhook_events');
    }
};
