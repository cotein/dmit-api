<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los planes del producto: los cuatro niveles que DMIT le vende a cada CUIT.
 *
 * OJO CON LOS NOMBRES: ya existen `subscription_plans` y `subscriptions`, que son
 * OTRA cosa — el cobro de la suscripción con Mercado Pago (preapproval). Estas
 * tablas nuevas son el catálogo que gobierna los topes de emisión. No se tocó nada
 * del flujo de cobro que ya funciona: la unificación de los dos catálogos es una
 * decisión aparte y todavía no está tomada.
 *
 * `position` y no `order`: ORDER es palabra reservada de MySQL y obligaría a
 * escapar el nombre en cada consulta.
 *
 * Sin CompanyScope: los planes son de la plataforma, no de una empresa. Si lo
 * llevaran, un usuario sin empresa no podría ni listarlos (mismo motivo por el que
 * `subscription_plans` tampoco lo lleva).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 64);
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('position');

            // Plan interno: el que se le asigna a los CUIT que ya existían al
            // momento de este corte. No se vende, no se muestra en la pantalla de
            // planes y no tiene topes. Existe para que nadie se despierte
            // bloqueado cuando se encienda el gating.
            $table->boolean('is_internal')->default(false);

            // Precio de lista. En este corte el cobro está SIMULADO: el importe se
            // muestra, no se debita nada.
            $table->decimal('price_amount', 12, 2)->default(0);
            $table->string('price_currency', 8)->default('ARS');

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
