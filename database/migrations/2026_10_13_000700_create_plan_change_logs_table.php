<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El historial de cambios de plan: quién, cuándo, de qué a qué y por qué.
 *
 * `source` distingue el cambio hecho por el dueño desde la pantalla ('customer'),
 * el que hizo la configuración interna ('internal') y el PEDIDO que dejó alguien
 * que no administra la empresa ('requested'), que queda registrado para que el
 * dueño lo vea. Un pedido no cambia nada por sí solo.
 *
 * `from_plan_id` es NULL en la asignación inicial (el backfill del plan interno):
 * no venía de ningún plan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_change_logs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('from_plan_id')->nullable();
            $table->unsignedBigInteger('to_plan_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('reason', 191)->nullable();
            $table->string('source', 16)->default('customer');

            $table->timestamps();

            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_change_logs');
    }
};
