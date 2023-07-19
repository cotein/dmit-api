<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePricelistProductsTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pricelist_products', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('pricelist_id')->unsigned()->nullable();
            $table->integer('product_id')->unsigned()->nullable();
            $table->float('price')->nullable()->default(0.00);
            $table->timestamps();
            $table->float('cost')->nullable()->default(0.00);
            $table->float('benefit', 12, 2)->nullable()->default(0.00);
            $table->boolean('active')->nullable()->default(1);
            $table->boolean('apply_discount')->nullable();
            $table->float('apply_discount_from', 10, 0)->nullable();
            $table->float('apply_discount_percentage', 10, 0)->nullable();
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('pricelist_products');
    }
}
