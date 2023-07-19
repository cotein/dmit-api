<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePedidosClientesTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pedidos_clientes', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('customer_id')->unsigned()->nullable();
            $table->string('code', 100)->nullable();
            $table->string('meli_id', 191)->nullable();
            $table->integer('number')->unsigned()->nullable();
            $table->date('delivery_date')->nullable();
            $table->float('total')->nullable()->default(0.00);
            $table->integer('status_id')->unsigned()->nullable();
            $table->integer('user_id')->unsigned()->nullable();
            $table->text('meli_data')->nullable();
            $table->timestamps();
            $table->integer('voucher_id')->unsigned()->default(101);
            $table->integer('parent_id')->unsigned()->nullable();
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('pedidos_clientes');
    }
}
