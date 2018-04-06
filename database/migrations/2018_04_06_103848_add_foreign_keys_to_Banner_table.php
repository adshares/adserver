<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddForeignKeysToBannerTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('banner', function(Blueprint $table)
		{
			$table->foreign('campaign_id', 'FK_6831BDD1F639F774')->references('id')->on('campaign')->onUpdate('RESTRICT')->onDelete('CASCADE');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('banner', function(Blueprint $table)
		{
			$table->dropForeign('FK_6831BDD1F639F774');
		});
	}

}
