<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddForeignKeysToNetworkCampaignModifierTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('network_campaign_modifier', function(Blueprint $table)
		{
			$table->foreign('campaign_id', 'FK_D08C8FC6F639F774')->references('id')->on('network_campaign')->onUpdate('RESTRICT')->onDelete('CASCADE');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('network_campaign_modifier', function(Blueprint $table)
		{
			$table->dropForeign('FK_D08C8FC6F639F774');
		});
	}

}
