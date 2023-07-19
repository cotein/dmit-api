<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRemitosTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('remitos', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->integer('pedido_cliente_id')->unsigned()->nullable();
            $table->string('code', 191)->nullable();
            $table->string('number', 191)->nullable();
            $table->string('delivery_date', 191)->nullable();
            $table->string('commercial_reference', 191)->nullable();
            $table->integer('payment_type_id')->unsigned()->nullable();
            $table->integer('status_id')->unsigned()->nullable();
            $table->integer('user_id')->unsigned()->nullable();
            $table->float('total', 14, 2)->unsigned()->nullable();
            $table->integer('customer_id')->unsigned()->nullable();
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('remitos');
    }
}
