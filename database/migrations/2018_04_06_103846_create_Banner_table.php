<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateBannerTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('banner', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->integer('campaign_id')->index('IDX_6831BDD1F639F774');
			$table->binary('creative_contents', 16777215);
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
		Schema::drop('banner');
	}

}
