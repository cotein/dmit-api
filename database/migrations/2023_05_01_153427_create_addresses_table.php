<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAddressesTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code', 191)->nullable();
            $table->integer('country_id')->unsigned()->nullable()->default(1);
            $table->integer('state_id')->unsigned()->nullable();
            $table->string('city', 191)->nullable();
            $table->string('street', 191)->nullable();
            $table->integer('number')->nullable();
            $table->string('cp', 31)->nullable();
            $table->text('obs', 65535)->nullable();
            $table->text('geocoder')->nullable();
            $table->integer('addressable_id')->unsigned()->nullable();
            $table->string('addressable_type', 191)->nullable();
            $table->integer('type_id')->unsigned()->nullable();
            $table->boolean('active')->nullable()->default(1);
            $table->string('between_streets', 191)->nullable();
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
        Schema::drop('addresses');
    }
}
