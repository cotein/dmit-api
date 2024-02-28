<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductsTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('products', function (Blueprint $table) {
            $table->increments('id');
            $table->string('meli_id', 191)->nullable();
            $table->integer('company_id')->unsigned()->default(1);
            $table->string('name', 191)->nullable();
            $table->string('code', 191)->nullable();
            $table->string('sub_title', 191)->nullable();
            $table->text('description', 65535)->nullable();
            $table->integer('iva_id')->nullable();
            $table->integer('money_id')->nullable();
            $table->integer('priority_id')->nullable();
            $table->boolean('published_meli')->nullable()->default(0);
            $table->boolean('published_here')->nullable()->default(0);
            $table->boolean('active')->default(1);
            $table->string('slug', 191)->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->integer('critical_stock')->unsigned()->nullable()->default(10);
            $table->boolean('sale_by_meters')->nullable()->default(0);
            $table->float('mts_by_unity', 10)->nullable();
            $table->boolean('apply_discount')->nullable()->default(0);
            $table->float('apply_discount_amount', 14, 2)->default(0);
            $table->float('apply_discount_percentage', 14, 2)->default(0);
            $table->boolean('see_price_on_the_web')->nullable()->default(0);

            $table->unique(['name', 'company_id']);
            $table->index(['name', 'code', 'company_id']);
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('products');
    }
}
