<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCompaniesTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('fantasy_name', 100)->nullable();
            $table->string('dni', 11)->nullable();
            $table->string('afip_number', 11)->nullable();
            $table->integer('afip_type')->unsigned()->nullable(); //física - jurídica
            $table->integer('afip_inscription_id')->unsigned()->nullable();
            $table->integer('afip_document_id')->unsigned()->nullable();
            $table->text('afip_data')->nullable();
            $table->boolean('percep_iibb')->default(0);
            $table->boolean('percep_iva')->default(0);
            $table->boolean('ret_suss')->default(0);
            $table->boolean('ret_iva')->default(0);
            $table->boolean('ret_iibb')->default(0);
            $table->boolean('ret_gcias')->default(0);
            $table->date('activity_init')->nullable();
            $table->string('iibb_conv', 191)->nullable();
            $table->string('environment', 191)->nullable();
            $table->json('settings')->nullable();
            $table->integer('pto_vta_fe')->unsigned()->nullable();
            $table->integer('pto_vta_fe_exterior')->unsigned()->nullable();
            $table->integer('pto_vta_fce')->unsigned()->nullable();
            $table->integer('pto_vta_fce_exterior')->unsigned()->nullable();
            $table->integer('pto_vta_remito')->unsigned()->nullable();
            $table->integer('pto_vta_remito_exterior')->unsigned()->nullable();
            $table->integer('pto_vta_recibo')->unsigned()->nullable();
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
        Schema::drop('companies');
    }
}
