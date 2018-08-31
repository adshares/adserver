<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUsersSettingsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('users_settings', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();

            $table->bigInteger('user_id')->unsigned();
            $table->string('type', 20);

            $table->text('payload')->nullable();

            $table->index('type');
            $table->foreign('user_id')->references('id')->on('users')->onUpdate('RESTRICT')->onDelete('CASCADE');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('users_settings');
    }
}
