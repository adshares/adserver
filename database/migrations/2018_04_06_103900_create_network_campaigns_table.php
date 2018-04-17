<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateNetworkCampaignsTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('network_campaigns', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->binary('uuid', 16); // REQ CUSTOM ALTER

            $table->timestamps();
            $table->softDeletes();

            $table->string('source_host'); // should link to network_hosts
            $table->integer('source_update_time')->unsigned(); // ? smelly

            $table->string('adshares_address', 32);

            $table->string('landing_url', 1024);

            $table->decimal('max_cpm');
            $table->decimal('max_cpc');
            $table->decimal('budget_per_hour');
            $table->dateTime('time_start');
            $table->dateTime('time_end');
        });

        DB::statement("ALTER TABLE network_campaigns MODIFY uuid varbinary(16)");

        Schema::table('network_campaigns', function (Blueprint $table) {
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
        Schema::drop('network_campaigns');
    }
}
