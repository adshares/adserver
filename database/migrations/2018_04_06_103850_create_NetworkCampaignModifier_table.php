<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateNetworkCampaignModifierTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('network_campaign_modifier', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->integer('campaign_id')->index('IDX_D08C8FC6F639F774');
			$table->binary('name', 64);
			$table->binary('min', 64);
			$table->binary('max', 64);
			$table->float('modifier', 10, 0);
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
		Schema::drop('network_campaign_modifier');
	}

}
