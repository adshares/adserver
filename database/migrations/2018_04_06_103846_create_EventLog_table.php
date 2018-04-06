<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateEventLogTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('EventLog', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->binary('cid', 16);
			$table->binary('tid', 16);
			$table->integer('publisher_event_id');
			$table->integer('banner_id');
			$table->string('event_type', 16);
			$table->binary('pay_to', 6)->nullable();
			$table->binary('ip', 8);
			$table->text('our_context')->nullable();
			$table->text('their_context')->nullable();
			$table->binary('user_id', 16)->nullable();
			$table->integer('human_score')->nullable();
			$table->text('our_userdata')->nullable();
			$table->text('their_userdata')->nullable();
			$table->integer('timestamp');
			$table->decimal('event_value', 20, 9)->nullable();
			$table->decimal('paid_amount', 20, 9);
			$table->integer('payment_id');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('EventLog');
	}

}
