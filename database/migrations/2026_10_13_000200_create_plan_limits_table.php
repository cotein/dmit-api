<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El tope de cada plan por recurso: un renglón por recurso.
 *
 * `limit_value` NULL significa SIN TOPE, y una fila ausente también: el cálculo del
 * tope vigente trata las dos cosas igual, porque el plan interno se carga sin
 * filas y no queremos que su lectura dependa de que existan renglones vacíos.
 *
 * El recurso es el mismo nombre que viaja en el contrato del rechazo
 * ('invoices', 'remitos') para que la pantalla no tenga que traducir nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_limits', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('plan_id');
            $table->string('resource', 32);
            $table->unsignedInteger('limit_value')->nullable();

            $table->timestamps();

            $table->unique(['plan_id', 'resource']);
            $table->foreign('plan_id')->references('id')->on('plans')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_limits');
    }
};
