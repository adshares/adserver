<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddForeignKeysToNetworkBannerTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('network_banner', function(Blueprint $table)
		{
			$table->foreign('campaign_id', 'FK_9F6DA3E4F639F774')->references('id')->on('network_campaign')->onUpdate('RESTRICT')->onDelete('CASCADE');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('network_banner', function(Blueprint $table)
		{
			$table->dropForeign('FK_9F6DA3E4F639F774');
		});
	}

}
