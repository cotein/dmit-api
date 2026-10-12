<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qué funciones trae cada plan: logo, marca de agua y cada integración, como sí/no.
 *
 * Misma forma que plan_limits: un renglón por función y ausencia = apagada. El
 * corte de branding y el de integraciones leen de acá para bloquear en pantalla Y
 * en el servidor; en este corte las filas se cargan igual para que el bloqueo ya
 * tenga de dónde leer cuando esas piezas se escriban.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_features', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('plan_id');
            $table->string('feature', 32);

            $table->timestamps();

            $table->unique(['plan_id', 'feature']);
            $table->foreign('plan_id')->references('id')->on('plans')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_features');
    }
};
