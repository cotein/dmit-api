<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRemitoItemsTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('remito_items', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('remito_id')->unsigned()->nullable();
            $table->float('quantity', 10)->unsigned()->nullable();
            $table->string('product_id', 191)->nullable();
            $table->float('neto', 10)->nullable()->default(0.00);
            $table->float('iva', 10)->nullable()->default(0.00);
            $table->float('total', 10)->nullable()->default(0.00);
            $table->timestamps();
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('remito_items');
    }
}
