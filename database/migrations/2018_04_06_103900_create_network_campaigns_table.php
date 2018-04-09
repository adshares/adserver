<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateNetworkCampaignsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('network_campaigns', function(Blueprint $table)
		{
			$table->bigIncrements('id');
			$table->integer('advertiser_id');
			$table->string('source_host');
			$table->integer('source_update_time')->unsigned();
			$table->string('adshares_address', 32);
			$table->binary('uuid', 16); // REQ CUSTOM ALTER
			$table->string('landing_url', 1024);
			$table->decimal('max_cpm');
			$table->decimal('max_cpc');
			$table->decimal('budget_per_hour');
			$table->dateTime('time_start');
			$table->dateTime('time_end');
			$table->integer('require_count');

			$table->timestamps();
			$table->softDeletes();
		});

		DB::statement("ALTER TABLE network_campaigns MODIFY uuid varbinary(16)");

		Schema::table('network_campaigns', function(Blueprint $table)
		{
			$table->unique('uuid');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('network_campaigns');
	}

}
