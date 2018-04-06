<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddForeignKeysToWebsiteRequireTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('WebsiteRequire', function(Blueprint $table)
		{
			$table->foreign('website_id', 'FK_284925A418F45C82')->references('id')->on('Website')->onUpdate('RESTRICT')->onDelete('CASCADE');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('WebsiteRequire', function(Blueprint $table)
		{
			$table->dropForeign('FK_284925A418F45C82');
		});
	}

}
