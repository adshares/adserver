<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateNetworkEventLogsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('network_event_logs', function(Blueprint $table)
		{
			$table->bigIncrements('id');
			$table->binary('cid', 16);
			$table->binary('tid', 16);
			$table->binary('banner_id', 16);
			$table->binary('pay_from', 6);
			$table->string('event_type', 16);
			$table->binary('ip', 8);
			$table->text('context')->nullable();
			$table->binary('user_id', 16)->nullable();
			$table->integer('human_score')->nullable();
			$table->text('our_userdata')->nullable();
			$table->text('their_userdata')->nullable();
			$table->integer('timestamp');
			$table->decimal('event_value', 20, 9);
			$table->decimal('paid_amount', 20, 9);
			$table->integer('payment_id');

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
		Schema::drop('network_event_logs');
	}

}
