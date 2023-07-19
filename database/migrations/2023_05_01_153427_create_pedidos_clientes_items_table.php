<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreatePedidosClientesItemsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('pedidos_clientes_items', function(Blueprint $table)
		{
			$table->increments('id');
			$table->integer('pedido_cliente_id')->unsigned()->nullable();
			$table->integer('product_id')->unsigned()->nullable();
			$table->integer('pricelist_id')->unsigned()->nullable();
			$table->float('unit_price', 10)->nullable()->default(0.00);
			$table->float('quantity', 10)->unsigned()->nullable()->default(1.00);
			$table->float('discount_percentage', 10)->unsigned()->nullable()->default(0.00);
			$table->float('discount_import', 10)->nullable()->default(0.00);
			$table->integer('iva_id')->unsigned()->nullable();
			$table->float('iva_percentage', 10)->nullable()->default(0.00);
			$table->float('iva_import', 10)->nullable()->default(0.00);
			$table->float('neto_import', 10)->nullable()->default(0.00);
			$table->float('total', 10)->nullable()->default(0.00);
			$table->text('price_list')->nullable();
			$table->timestamps();
			$table->boolean('is_chp')->nullable();
			$table->float('mts')->nullable();
			$table->float('rounded_mts')->nullable();
			$table->float('real_mts', 12)->nullable();
			$table->float('mts_to_invoiced', 12)->nullable();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('pedidos_clientes_items');
	}

}
