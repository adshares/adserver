<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddForeignKeysToNetworkCampaignRequireTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('network_campaign_require', function(Blueprint $table)
		{
			$table->foreign('campaign_id', 'FK_C35BF282F639F774')->references('id')->on('network_campaign')->onUpdate('RESTRICT')->onDelete('CASCADE');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('network_campaign_require', function(Blueprint $table)
		{
			$table->dropForeign('FK_C35BF282F639F774');
		});
	}

}
