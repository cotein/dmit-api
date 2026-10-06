<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMeliTokensTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('meli_tokens', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('company_id')->nullable();
            $table->integer('meli_user_id')->nullable();
            $table->integer('user_id')->nullable();
            $table->string('token_type', 191)->nullable()->default('bearer');
            $table->text('meli_token', 65535)->nullable();
            $table->text('meli_refresh_token', 65535)->nullable();
            $table->string('meli_token_expiration_time', 191)->nullable();
            $table->string('meli_email', 191)->nullable();
            $table->boolean('active')->nullable()->default(0);
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
        Schema::drop('meli_tokens');
    }
}
