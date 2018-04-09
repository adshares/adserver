<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateNetworkHostsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('network_hosts', function(Blueprint $table)
		{
			$table->string('address', 6)->primary(); // binary // REQ CUSTOM ALTER
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

		DB::statement("ALTER TABLE network_hosts MODIFY address varbinary(6)");
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('network_hosts');
	}

}
