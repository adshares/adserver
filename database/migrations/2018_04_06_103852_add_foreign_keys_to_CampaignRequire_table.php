<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddForeignKeysToCampaignRequireTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('campaign_require', function(Blueprint $table)
		{
			$table->foreign('campaign_id', 'FK_222EFE64F639F774')->references('id')->on('campaign')->onUpdate('RESTRICT')->onDelete('CASCADE');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('campaign_require', function(Blueprint $table)
		{
			$table->dropForeign('FK_222EFE64F639F774');
		});
	}

}
