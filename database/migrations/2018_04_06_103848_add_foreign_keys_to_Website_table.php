<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddForeignKeysToWebsiteTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('Website', function(Blueprint $table)
		{
			$table->foreign('user_id', 'FK_88D2647BA76ED395')->references('id')->on('User')->onUpdate('RESTRICT')->onDelete('CASCADE');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('Website', function(Blueprint $table)
		{
			$table->dropForeign('FK_88D2647BA76ED395');
		});
	}

}
