<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateZoneTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('zone', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->integer('website_id')->nullable()->index('IDX_D96F3918F45C82');
			$table->string('name', 32);
			$table->integer('width');
			$table->integer('height');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('zone');
	}

}
