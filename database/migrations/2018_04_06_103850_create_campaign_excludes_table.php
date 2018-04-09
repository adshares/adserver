<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateCampaignExcludesTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('campaign_excludes', function(Blueprint $table)
		{
			$table->bigIncrements('id');
			$table->bigInteger('campaign_id')->unsigned();
			$table->binary('name', 64);
			$table->binary('min', 64);
			$table->binary('max', 64);
			$table->timestamps();
			$table->softDeletes();
			// TODO: add these indexes (191 chars max)
			// $table->index(['campaign_id','name','min'], 'min');
			// $table->index(['campaign_id','name','max'], 'max');
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
