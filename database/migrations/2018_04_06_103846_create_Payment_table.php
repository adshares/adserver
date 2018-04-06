<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreatePaymentTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('payment', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->text('transfers');
			$table->text('subthreshold_transfers')->nullable();
			$table->integer('create_time');
			$table->binary('account_address', 6)->nullable();
			$table->binary('account_hashin', 32)->nullable();
			$table->binary('account_hashout', 32)->nullable();
			$table->integer('account_msid');
			$table->text('tx_data');
			$table->binary('tx_id', 8);
			$table->integer('tx_time');
			$table->decimal('fee', 20, 9);
			$table->boolean('completed');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('payment');
	}

}
