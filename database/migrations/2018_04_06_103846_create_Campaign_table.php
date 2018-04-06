<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateCampaignTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('Campaign', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->integer('user_id')->index('IDX_E663708BA76ED395');
			$table->binary('uuid', 16);
			$table->string('landing_url', 1024);
			$table->decimal('max_cpm');
			$table->decimal('max_cpc');
			$table->decimal('budget_per_hour');
			$table->dateTime('time_start');
			$table->dateTime('time_end');
			$table->integer('require_count');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('Campaign');
	}

}
