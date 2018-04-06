<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddForeignKeysToCampaignExcludeTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('CampaignExclude', function(Blueprint $table)
		{
			$table->foreign('campaign_id', 'FK_18A7E55F639F774')->references('id')->on('Campaign')->onUpdate('RESTRICT')->onDelete('CASCADE');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('CampaignExclude', function(Blueprint $table)
		{
			$table->dropForeign('FK_18A7E55F639F774');
		});
	}

}
