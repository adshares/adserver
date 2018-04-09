<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateCampaignExcludeTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('campaign_excludes', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->integer('campaign_id');
			$table->binary('name', 64);
			$table->binary('min', 64);
			$table->binary('max', 64);
			$table->index(['campaign_id','name','min'], 'min');
			$table->index(['campaign_id','name','max'], 'max');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('campaign_excludes');
	}

}
