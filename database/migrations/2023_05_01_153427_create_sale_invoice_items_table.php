<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSaleInvoiceItemsTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sale_invoice_items', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('sale_invoice_id')->unsigned()->nullable();
            $table->integer('product_id')->unsigned()->nullable();
            $table->float('quantity', 10)->unsigned()->nullable();
            $table->float('neto_import', 10)->nullable();
            $table->float('iva_import', 10)->nullable();
            $table->integer('iva_id')->unsigned()->nullable();
            $table->float('discount_percentage', 10, 2)->unsigned()->nullable();
            $table->float('discount_import', 10)->nullable();
            $table->float('total', 14, 2)->nullable();
            $table->string('obs', 191)->nullable();
            $table->timestamps();
            $table->float('unit_price', 14)->nullable()->default(0.00);
            $table->integer('price_list_id')->nullable();
            $table->integer('voucher_id')->nullable();
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('sale_invoice_items');
    }
}
