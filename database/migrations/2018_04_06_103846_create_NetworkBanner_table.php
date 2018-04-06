<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateNetworkBannerTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('NetworkBanner', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->integer('campaign_id')->index('IDX_9F6DA3E4F639F774');
			$table->string('serve_url', 1024);
			$table->string('click_url', 1024);
			$table->string('view_url', 1024);
			$table->binary('uuid', 16);
			$table->string('creative_type', 32);
			$table->binary('creative_sha1', 20);
			$table->integer('creative_width');
			$table->integer('creative_height');
			$table->dateTime('modify_time');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('NetworkBanner');
	}

}
