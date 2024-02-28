<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCustomersTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('fantasy_name', 100)->nullable();
            $table->string('dni', 11)->nullable();
            $table->string('afip_number', 11)->nullable();
            $table->integer('afip_inscription_id')->unsigned()->nullable();
            $table->integer('afip_document_id')->unsigned()->nullable();
            $table->integer('afip_type')->unsigned()->nullable();
            $table->string('contact', 191)->nullable();
            $table->text('afip_data')->nullable();
            $table->timestamps();
            $table->string('cell_phone', 191)->nullable();
            $table->string('phone_1', 191)->nullable();
            $table->string('phone_2', 191)->nullable();
            $table->string('phone_3', 191)->nullable();
            $table->string('email', 191)->nullable();
            $table->text('obs')->nullable();
            $table->boolean('active')->nullable()->default(1);
            $table->string('meli_id', 191)->nullable();
            $table->string('meli_nick', 191)->nullable();
            $table->integer('pay_condition')->unsigned()->nullable();
            $table->integer('customer_type_id')->unsigned()->nullable()->default(1);
            $table->integer('company_id')->unsigned()->nullable();
            $table->integer('user_id')->unsigned()->nullable();

            $table->index(['name', 'afip_number']);
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('customers');
    }
}
