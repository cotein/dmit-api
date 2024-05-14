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
        Schema::create('sale_invoices', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('company_id')->unsigned()->nullable();
            $table->integer('customer_id')->unsigned()->nullable();
            $table->integer('voucher_id')->unsigned()->nullable();
            $table->integer('pto_vta')->unsigned()->nullable();
            $table->integer('cbte_desde')->unsigned()->nullable();
            $table->integer('cbte_hasta')->unsigned()->nullable();
            $table->date('cbte_fch')->nullable();
            $table->string('cae', 100)->nullable();
            $table->date('cae_fch_vto')->nullable();
            $table->integer('user_id')->unsigned()->nullable();
            $table->text('afip_data')->nullable();
            $table->timestamps();
            $table->date('vto_payment')->nullable();
            $table->string('commercial_reference', 191)->nullable();
            $table->integer('payment_type_id')->unsigned()->nullable();
            $table->integer('sales_condition_id')->unsigned()->nullable();
            $table->integer('status_id')->unsigned()->nullable();
            $table->date('fch_serv_desde')->nullable();
            $table->date('fch_serv_hasta')->nullable();
            $table->integer('parent_id')->unsigned()->nullable();
            $table->date('fch_vto_pago')->nullable();

            $table->index(['cbte_desde', 'customer_id', 'company_id']);
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('sale_invoices');
    }
}
