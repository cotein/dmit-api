<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAfipTokensTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('afip_tokens', function (Blueprint $table) {
            $table->increments('id');
            $table->string('ws', 191)->nullable();
            $table->string('environment', 191)->nullable();
            $table->string('unique_id', 191)->nullable();
            $table->longText('token')->nullable();
            $table->longText('sign')->nullable();
            $table->integer('company_id')->unsigned()->nullable()->default(1);
            $table->integer('user_id')->unsigned()->nullable();
            $table->dateTime('generation_time')->nullable();
            $table->dateTime('expiration_time')->nullable();
            $table->boolean('active')->nullable()->default(1);
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
        Schema::drop('afip_tokens');
    }
}
