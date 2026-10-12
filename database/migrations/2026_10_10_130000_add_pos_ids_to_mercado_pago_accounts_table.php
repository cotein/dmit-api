<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sucursal y caja de Mercado Pago asociadas a la cuenta de una empresa.
 *
 * El QR del mostrador se crea contra una "caja" (POS) de MP: sin esos ids no se
 * puede emitir un QR dinámico. Se guardan acá porque son de la cuenta, no del
 * cobro: se crean una vez y se reutilizan en todos los QRs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mercado_pago_accounts', function (Blueprint $table) {
            $table->string('store_external_id')->nullable()->after('mp_user_id');
            $table->string('pos_external_id')->nullable()->after('store_external_id');
        });
    }

    public function down(): void
    {
        Schema::table('mercado_pago_accounts', function (Blueprint $table) {
            $table->dropColumn(['store_external_id', 'pos_external_id']);
        });
    }
};
