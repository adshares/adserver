<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateClassificationTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('classification', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();

            $table->bigInteger('user_id')->unsigned();
            $table->bigInteger('site_id')->unsigned()->nullable();
            $table->bigInteger('banner_id')->unsigned();
            $table->string('signature', 16);
            $table->unsignedTinyInteger('status')->nullable();

            $table
                ->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('RESTRICT')
                ->onDelete('CASCADE');

            $table
                ->foreign('site_id')
                ->references('id')
                ->on('sites')
                ->onUpdate('RESTRICT')
                ->onDelete('CASCADE');

            $table
                ->foreign('banner_id')
                ->references('id')
                ->on('network_banners')
                ->onUpdate('RESTRICT')
                ->onDelete('CASCADE');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('classification');
    }
}
