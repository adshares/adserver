<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateEventLogsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('event_logs', function(Blueprint $table)
		{
			$table->bigIncrements('id');
			$table->binary('cid', 16); // REQ CUSTOM ALTER
			$table->binary('tid', 16); // REQ CUSTOM ALTER
			$table->integer('publisher_event_id');
			$table->bigInteger('banner_id');
			$table->string('event_type', 16);
			$table->binary('pay_to', 6)->nullable(); // REQ CUSTOM ALTER
			$table->binary('ip', 8); // REQ CUSTOM ALTER
			$table->text('our_context')->nullable();
			$table->text('their_context')->nullable();
			$table->binary('user_id', 16)->nullable(); // REQ CUSTOM ALTER
			$table->integer('human_score')->nullable();
			$table->text('our_userdata')->nullable();
			$table->text('their_userdata')->nullable();
			$table->integer('timestamp');
			$table->decimal('event_value', 20, 9)->nullable();
			$table->decimal('paid_amount', 20, 9);
			$table->integer('payment_id');

			$table->timestamps();
			$table->softDeletes();
		});

		DB::statement("ALTER TABLE event_logs MODIFY cid varbinary(16)");
		DB::statement("ALTER TABLE event_logs MODIFY tid varbinary(16)");
		DB::statement("ALTER TABLE event_logs MODIFY pay_to varbinary(6)");
		DB::statement("ALTER TABLE event_logs MODIFY ip varbinary(8)");
		DB::statement("ALTER TABLE event_logs MODIFY user_id varbinary(16)");
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('event_logs');
	}

}
