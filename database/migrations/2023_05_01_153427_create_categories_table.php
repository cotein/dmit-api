<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCategoriesTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code', 191)->nullable();
            $table->integer('parent_id')->nullable()->default(0);
            $table->string('name', 191)->nullable();
            $table->string('slug', 191)->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->text('attributes')->nullable();
            $table->boolean('active')->nullable()->default(1);
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('categories');
    }
}
