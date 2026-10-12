<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Las reservas de consumo: el número que se toma ANTES de llamar al organismo.
 *
 * POR QUÉ EXISTE ESTA TABLA: el comprobante se cuenta cuando ARCA lo autoriza, pero
 * entre el pedido y la respuesta pasan segundos. Sin reserva, dos emisiones
 * simultáneas con el tope a una entrarían las dos, porque las dos leerían el mismo
 * contador. La reserva hace que el segundo pedido vea el lugar ocupado sin
 * bloquear la tabla durante toda la llamada a ARCA.
 *
 * La reserva VENCE SOLA (`expires_at`, pocos minutos): si el proceso se corta entre
 * la reserva y la confirmación, el lugar se libera sin que nadie intervenga. Eso lo
 * hace el saneamiento que corre cada pocos minutos.
 *
 * `key_hash` es la clave de idempotencia del intento: un reintento del mismo pedido
 * (el caso clásico es un timeout del navegador con reenvío) cae en la MISMA reserva
 * y no consume dos veces. Se guarda el hash y nunca el contenido del pedido.
 *
 * `invoice_ref` guarda con qué comprobante terminó confirmada la reserva, para
 * poder auditar y para que el recálculo pueda cruzarla contra los comprobantes del
 * periodo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_usage_reservations', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('company_id');
            $table->string('period', 7);
            $table->string('resource', 32);
            $table->string('key_hash', 64);

            // reserved | confirmed | released | expired
            $table->string('status', 16)->default('reserved');

            $table->timestamp('expires_at');
            $table->timestamp('confirmed_at')->nullable();

            $table->unsignedBigInteger('invoice_id')->nullable();

            $table->timestamps();

            $table->unique(['company_id', 'resource', 'key_hash']);
            $table->index(['company_id', 'period', 'resource', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_usage_reservations');
    }
};
