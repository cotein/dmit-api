<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La suscripción de cada CUIT a un plan: UNA fila por empresa.
 *
 * Una sola fila (company_id UNIQUE) y no un historial acá: el historial completo
 * vive en `plan_change_logs`. Así "cuál es el plan de este CUIT" es una lectura por
 * índice único, sin tener que ordenar por fecha, y no puede haber dos
 * suscripciones vigentes por un error de carga.
 *
 * El estado es el que decide el plan efectivo:
 *   - active    -> el plan asignado, con sus topes;
 *   - expired / suspended -> cae al plan gratuito, CON motivo (un problema
 *     administrativo no puede cortar la facturación de golpe);
 *   - sin fila  -> plan gratuito SIN motivo: una cuenta nueva no está degradada.
 *
 * `requested_plan_id` es el pedido de cambio que dejó alguien que NO administra la
 * empresa: queda registrado para el dueño. Va en esta misma fila para que no pueda
 * haber dos pedidos pendientes por CUIT.
 *
 * `gating_enabled` es el interruptor POR CUIT (NULL = hereda del interruptor
 * general). Vive acá y no en una pantalla porque es configuración interna: sirve
 * para encender el corte en un solo contribuyente de prueba.
 *
 * OJO: company_id es BIGINT UNSIGNED y SIN foreign key a `companies`, por el mismo
 * motivo documentado en 2026_10_10_120500_create_subscriptions_table.php:
 * companies.id es INT UNSIGNED en los migrations viejos y BIGINT UNSIGNED en la
 * base real, así que una FK funciona en una y rompe en la otra (MySQL 3780).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_plans', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('plan_id');

            $table->string('status', 16)->default('active');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('suspended_at')->nullable();

            $table->unsignedBigInteger('assigned_by')->nullable();

            $table->unsignedBigInteger('requested_plan_id')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();

            $table->boolean('gating_enabled')->nullable();

            $table->timestamps();

            $table->unique('company_id');
            $table->index('status');

            $table->foreign('plan_id')->references('id')->on('plans');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_plans');
    }
};
