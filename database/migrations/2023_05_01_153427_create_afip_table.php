<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAfipTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('afip', function (Blueprint $table) {
            $table->increments('id');
            $table->string('ws', 191)->nullable();
            $table->string('unique_id', 191)->nullable();
            $table->string('generation_time', 191)->nullable();
            $table->string('expiration_time', 191)->nullable();
            $table->text('token', 65535)->nullable();
            $table->text('sign', 65535)->nullable();
            $table->string('environment', 191)->nullable();
            $table->boolean('active')->nullable();
            $table->integer('company_id')->nullable();
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
        Schema::drop('afip');
    }
}
