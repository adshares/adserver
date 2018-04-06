<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateNetworkPaymentTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('NetworkPayment', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->binary('receiver_address', 6);
			$table->binary('sender_address', 6);
			$table->string('sender_host', 32);
			$table->decimal('amount');
			$table->integer('create_time');
			$table->string('tx_id', 128);
			$table->integer('tx_time');
			$table->boolean('detailed_data_used');
			$table->boolean('processed');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('NetworkPayment');
	}

}
