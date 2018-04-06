<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddForeignKeysToWebsiteExcludeTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('WebsiteExclude', function(Blueprint $table)
		{
			$table->foreign('website_id', 'FK_BEDA59518F45C82')->references('id')->on('Website')->onUpdate('RESTRICT')->onDelete('CASCADE');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('WebsiteExclude', function(Blueprint $table)
		{
			$table->dropForeign('FK_BEDA59518F45C82');
		});
	}

}
