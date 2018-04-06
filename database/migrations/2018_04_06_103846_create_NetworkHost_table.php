<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateNetworkHostTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('NetworkHost', function(Blueprint $table)
		{
			$table->binary('address', 6)->primary();
			$table->string('host', 128);
			$table->integer('account_msid')->unsigned();
			$table->decimal('fee_estimate');
			$table->decimal('received_payments');
			$table->decimal('expected_payments');
			$table->integer('event_count')->unsigned();
			$table->float('credibility', 10, 0);
			$table->float('score', 10, 0);
			$table->integer('last_seen')->unsigned();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('NetworkHost');
	}

}
