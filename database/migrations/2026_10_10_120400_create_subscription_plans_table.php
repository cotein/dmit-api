<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Planes de suscripción de DMIT (lo que DMIT le cobra a cada comercio por usar
 * el sistema). Son de plataforma: NO pertenecen a ninguna empresa y no llevan
 * CompanyScope. El cobro de estas suscripciones usa las credenciales de DMIT
 * (config del sistema), no el OAuth de cada comercio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 8)->default('ARS');
            $table->string('period')->default('monthly'); // monthly | yearly
            $table->unsignedSmallInteger('free_trial_days')->default(0);

            // Plan espejo en Mercado Pago (preapproval_plan), si se usa el flujo con plan.
            $table->string('mp_preapproval_plan_id')->nullable();

            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
