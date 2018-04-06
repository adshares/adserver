<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddForeignKeysToZoneTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('zone', function(Blueprint $table)
		{
			$table->foreign('website_id', 'FK_D96F3918F45C82')->references('id')->on('website')->onUpdate('RESTRICT')->onDelete('CASCADE');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('zone', function(Blueprint $table)
		{
			$table->dropForeign('FK_D96F3918F45C82');
		});
	}

}
