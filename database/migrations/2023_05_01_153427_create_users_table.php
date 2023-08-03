<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 191)->nullable();
            $table->string('last_name', 191)->nullable();
            $table->string('email', 191)->nullable()->unique();
            $table->string('password', 191)->nullable();
            $table->boolean('active')->default(0);
            $table->boolean('confirmed')->default(0);
            $table->string('confirmation_code')->nullable();
            $table->integer('type_user_id')->unsigned()->default(1);
            $table->integer('company_id')->unsigned()->nullable()->default();
            $table->dateTime('email_verified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('users');
    }
}
