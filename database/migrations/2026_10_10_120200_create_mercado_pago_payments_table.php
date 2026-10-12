<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El PAGO real que devuelve Mercado Pago. Es la fuente de verdad del estado
 * (un cobro puede tener varios intentos: rechazado y después aprobado).
 *
 * Se guarda el payload crudo en `raw` porque el estado que muestra la app tiene
 * que poder auditarse contra lo que contestó MP en ese momento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mercado_pago_payments', function (Blueprint $table) {
            $table->id();

            // companies.id: BIGINT en la base real, INT en los migrations. Ver nota.
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('charge_id')->nullable();

            $table->string('mp_payment_id')->unique();
            $table->string('mp_merchant_order_id')->nullable();
            $table->string('mp_preapproval_id')->nullable();

            $table->string('status');
            $table->string('status_detail')->nullable();

            $table->decimal('amount', 14, 2);
            $table->decimal('fee_amount', 14, 2)->nullable();
            $table->decimal('net_amount', 14, 2)->nullable();

            $table->string('payment_method_id')->nullable();
            $table->string('payment_type')->nullable();
            $table->unsignedSmallInteger('installments')->nullable();

            $table->string('payer_email')->nullable();
            $table->string('payer_id')->nullable();
            $table->string('external_reference')->nullable();

            $table->json('raw')->nullable();

            $table->timestamp('approved_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index('charge_id');
            $table->index('external_reference');


            /*
             * SIN foreign key a companies por el mismo motivo que en
             * mercado_pago_accounts: companies.id es INT UNSIGNED en los
             * migrations y BIGINT UNSIGNED en la base real, así que una FK a esa
             * tabla funciona en una y rompe en la otra (MySQL 3780). La columna
             * es BIGINT UNSIGNED para aceptar ids de los dos orígenes.
             */
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mercado_pago_payments');
    }
};
