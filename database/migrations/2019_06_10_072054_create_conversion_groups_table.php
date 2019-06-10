<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateConversionGroupsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('conversion_groups', static function (Blueprint $table) {
            $table->increments('id');
            $table->binary('group_id', 16);
            $table->bigInteger('conversion_definition_id')->unsigned();
            $table->bigInteger('value')->unsigned()->nullable();
            $table->decimal('weight', 3, 2);
        });

        if (DB::isMysql()) {
            DB::statement('ALTER TABLE conversion_groups MODIFY group_id varbinary(16)');
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('conversion_groups');
    }
}
