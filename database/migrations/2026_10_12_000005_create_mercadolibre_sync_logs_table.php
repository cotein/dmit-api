<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Historial de cada corrida de sincronización.
     *
     * Es lo que se muestra en la pantalla de MercadoLibre cuando el usuario
     * pregunta "¿por qué no veo mi publicación nueva?". Sin esto, un error de
     * sincronización es invisible: el import corre en un comando y nadie lee la
     * salida de la consola.
     */
    public function up(): void
    {
        Schema::create('mercadolibre_sync_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('company_id')->unsigned();
            $table->unsignedInteger('mercadolibre_account_id')->nullable();

            // import | push | orders | webhook | match | reconcile | publish
            $table->string('type', 30);
            // running | ok | partial | failed
            $table->string('status', 20)->default('running');

            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();

            $table->unsignedInteger('processed')->default(0);
            $table->unsignedInteger('created')->default(0);
            $table->unsignedInteger('updated')->default(0);
            $table->unsignedInteger('skipped')->default(0);
            $table->unsignedInteger('failed')->default(0);

            $table->string('message', 255)->nullable();
            // Lista acotada de errores (no el log entero): sirve para mostrar
            // los primeros problemas en pantalla sin llenar la base.
            $table->json('errors')->nullable();
            $table->json('context')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'type', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mercadolibre_sync_logs');
    }
};
