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
		Schema::table('website_require', function(Blueprint $table)
		{
			$table->foreign('website_id', 'FK_284925A418F45C82')->references('id')->on('website')->onUpdate('RESTRICT')->onDelete('CASCADE');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('wbsite_require', function(Blueprint $table)
		{
			$table->dropForeign('FK_284925A418F45C82');
		});
	}

}
