<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suscripción de una empresa a un plan de DMIT.
 *
 * `mp_preapproval_id` es el acuerdo en Mercado Pago: cuando MP cobra (o falla el
 * cobro), llega por webhook y actualiza status y next_payment_at. El acceso al
 * sistema se decide por este estado, no por una fecha calculada aparte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();

            // companies.id: BIGINT en la base real, INT en los migrations. Ver nota.
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('plan_id');
            $table->unsignedBigInteger('payer_user_id')->nullable();

            $table->string('mp_preapproval_id')->nullable()->unique();
            $table->string('payer_email')->nullable();

            // pending | authorized | paused | cancelled
            $table->string('status')->default('pending');

            $table->timestamp('started_at')->nullable();
            $table->timestamp('next_payment_at')->nullable();
            $table->timestamp('last_payment_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->foreign('plan_id')->references('id')->on('subscription_plans');

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
        Schema::dropIfExists('subscriptions');
    }
};
