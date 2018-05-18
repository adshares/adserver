<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateCampaignsTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->binary('uuid', 16); // REQ CUSTOM ALTER

            $table->timestamps();
            $table->softDeletes();

            $table->bigInteger('user_id')->unsigned();

            $table->string('landing_url', 1024);

            $table->decimal('max_cpm');
            $table->decimal('max_cpc');
            $table->decimal('budget_per_hour');

            $table->dateTime('time_start');
            $table->dateTime('time_end');
        });

        DB::statement("ALTER TABLE campaigns MODIFY uuid varbinary(16) NOT NULL");

        Schema::table('campaigns', function (Blueprint $table) {
            $table->unique('uuid');
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('campaigns');
    }
}
