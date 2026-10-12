<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El contador del mes por CUIT y por recurso.
 *
 * `period` es el MES CALENDARIO ('2026-12'), no una ventana móvil: el contador se
 * renueva el 1° y lo no usado no se acumula. Se guarda como texto y no como fecha
 * para que la clave única sea legible en un SELECT a mano y para que dos meses no
 * puedan convivir con formatos distintos de fecha.
 *
 * La combinación (empresa, periodo, recurso) es única: es la garantía de que el
 * descuento no puede duplicarse por dos pedidos simultáneos.
 *
 * Los contadores NO se borran. Un mes viejo se queda con su número real, aunque
 * después se baje de plan: la pantalla tiene que poder explicar ese caso sin
 * parecer una falla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_usage_counters', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('company_id');
            $table->string('period', 7);
            $table->string('resource', 32);
            $table->unsignedInteger('used')->default(0);

            $table->timestamps();

            $table->unique(['company_id', 'period', 'resource']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_usage_counters');
    }
};
