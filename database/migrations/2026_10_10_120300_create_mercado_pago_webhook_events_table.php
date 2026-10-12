<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitácora de notificaciones de Mercado Pago con dos objetivos:
 *
 *  1. IDEMPOTENCIA: MP reintenta y duplica webhooks. `mp_event_id` es único, así
 *     que un evento repetido se detecta y no vuelve a mover el estado del cobro.
 *  2. AUDITORÍA: se guarda el payload crudo y si la firma validó, porque un pago
 *     que "no llegó" se investiga acá.
 *
 * Sin CompanyScope a propósito: el webhook llega sin usuario autenticado y el
 * scope fail-closed haría que la consulta no devuelva nada. La empresa se
 * resuelve después, a partir del cobro referenciado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mercado_pago_webhook_events', function (Blueprint $table) {
            $table->id();

            $table->string('mp_event_id')->nullable()->unique();
            $table->string('topic')->nullable();   // payment | merchant_order | preapproval | ...
            $table->string('action')->nullable();
            $table->string('resource_id')->nullable();

            $table->unsignedInteger('company_id')->nullable();
            $table->boolean('signature_valid')->default(false);

            $table->json('payload');
            $table->string('status')->default('received'); // received | processed | ignored | failed
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('processed_at')->nullable();

            $table->timestamps();

            $table->index(['topic', 'resource_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mercado_pago_webhook_events');
    }
};
