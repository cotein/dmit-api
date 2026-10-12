<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La configuración interna del corte de planes: clave y valor.
 *
 * Vive en la base y no en el .env porque el interruptor general tiene que poder
 * apagarse desde la configuración interna en el momento (es la vuelta atrás del
 * deploy: apagar el gating y todos facturan como antes en un minuto), y porque el
 * valor tiene que ser el mismo para todos los procesos, sin reiniciar nada.
 *
 * Esta tabla NUNCA se expone en la pantalla del cliente: son llaves de operación.
 *
 * Claves que usa este corte:
 *   - gating.enabled            -> '1' enciende el corte para todos (por defecto
 *                                 no existe la fila, o sea apagado);
 *   - gating.enabled.company.{id} -> '1'/'0' enciende o apaga un CUIT puntual,
 *                                 que es como se prueba antes de ir a todos;
 *   - usage.reservation_minutes  -> cuántos minutos vive una reserva sin confirmar;
 *   - usage.grace_remaining      -> con cuántas restantes aparece el aviso suave
 *                                 (por defecto 2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_settings', function (Blueprint $table) {
            $table->id();

            $table->string('key', 96)->unique();
            $table->text('value')->nullable();
            $table->string('description', 191)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_settings');
    }
};
