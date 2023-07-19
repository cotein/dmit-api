<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMoneysTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('moneys', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code', 191);
            $table->string('name', 191);
            $table->string('symbol', 191);
            $table->string('value', 191);
            $table->string('description', 191);
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
        Schema::drop('moneys');
    }
}
