<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddForeignKeysToNetworkBannersTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('network_banners', function(Blueprint $table)
		{
			$table->foreign('campaign_id')->references('id')->on('network_campaigns')->onUpdate('RESTRICT')->onDelete('CASCADE');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('network_banners', function(Blueprint $table)
		{
			$table->dropForeign(['campaign_id']);
		});
	}

}
