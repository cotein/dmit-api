<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSalesInvoicesTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sales_invoices', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('company_id')->unsigned()->nullable();
            $table->integer('customer_id')->unsigned()->nullable();
            $table->integer('voucher_id')->unsigned()->nullable();
            $table->integer('pto_vta')->unsigned()->nullable();
            $table->integer('cbte_desde')->unsigned()->nullable();
            $table->integer('cbte_hasta')->unsigned()->nullable();
            $table->string('cbte_fch', 100)->nullable();
            $table->string('cae', 100)->nullable();
            $table->string('cae_fch_vto', 100)->nullable();
            $table->integer('status_id')->unsigned()->nullable();
            $table->integer('user_id')->unsigned()->nullable();
            $table->text('afip_data')->nullable();
            $table->timestamps();
            $table->string('vto_payment', 191)->nullable();
            $table->string('commercial_reference', 191)->nullable();
            $table->integer('payment_type_id')->unsigned()->nullable();
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
        Schema::drop('sales_invoices');
    }
}
