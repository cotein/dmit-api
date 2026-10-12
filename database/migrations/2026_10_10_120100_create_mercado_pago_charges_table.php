<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un COBRO generado por la app hacia un pagador, en cualquiera de los canales:
 *
 *  - link       → Checkout Pro: se comparte el init_point por WhatsApp/mail.
 *  - pos_qr     → QR dinámico en el mostrador contra una venta.
 *  - point      → cobro con la terminal Mercado Pago Point.
 *  - subscription → alta de suscripción (preapproval).
 *
 * `external_reference` es NUESTRO identificador y es lo que vuelve en el
 * webhook: es la única forma confiable de reconciliar un pago con su origen
 * (un comprobante, una cuenta corriente o una venta).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mercado_pago_charges', function (Blueprint $table) {
            $table->id();

            // companies.id: BIGINT en la base real, INT en los migrations. Ver nota.
            $table->unsignedBigInteger('company_id');

            // A qué se imputa el cobro (todas opcionales: un cobro manual no tiene ninguna).
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('sale_invoice_id')->nullable();
            $table->unsignedBigInteger('receipt_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();

            $table->string('channel')->default('link');   // link | pos_qr | point | subscription
            $table->string('concept')->default('manual'); // invoice | current_account | sale | manual | subscription

            $table->string('external_reference')->unique();
            $table->string('preference_id')->nullable();
            $table->string('mp_order_id')->nullable();
            $table->text('init_point')->nullable();
            $table->text('sandbox_init_point')->nullable();

            $table->decimal('amount', 14, 2);
            $table->string('currency', 8)->default('ARS');
            $table->string('title')->nullable();
            $table->text('description')->nullable();

            // pending | in_process | approved | rejected | cancelled | refunded | expired
            $table->string('status')->default('pending');
            $table->string('status_detail')->nullable();

            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index('customer_id');
            $table->index('sale_invoice_id');
            $table->index('receipt_id');
            $table->index('preference_id');
            $table->index('mp_order_id');


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
        Schema::dropIfExists('mercado_pago_charges');
    }
};
