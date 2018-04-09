<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateNetworkBannersTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('network_banners', function(Blueprint $table)
		{
			// TODO: missing creative contents (?)

			$table->bigIncrements('id');
			$table->bigInteger('campaign_id')->unsigned();
			$table->string('serve_url', 1024);
			$table->string('click_url', 1024);
			$table->string('view_url', 1024);
			$table->binary('uuid', 16); // REQ CUSTOM ALTER
			$table->string('creative_type', 32);
			$table->binary('creative_sha1', 20); // REQ CUSTOM ALTER
			$table->integer('creative_width');
			$table->integer('creative_height');
			$table->dateTime('modify_time');

			$table->timestamps();
			$table->softDeletes();
		});

		DB::statement("ALTER TABLE network_banners MODIFY uuid varbinary(16)");
		DB::statement("ALTER TABLE network_banners MODIFY creative_sha1 varbinary(20)");
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('network_banners');
	}

}
