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
            $table->timestamp('source_created_at')->nullable();
            $table->timestamp('source_updated_at')->nullable();

            $table->string('source_host'); // should link to network_hosts

            // TODO: Jacek? do we really need that / not really seen it being used outside of prop set()
            // $table->integer('source_update_time')->unsigned(); // ? smelly
            // TODO: Jacek? why is that here and why the format is string -> is that account of the user that is doing the campaign?
            $table->string('adshares_address', 32);

            $table->string('landing_url', 1024);

            $table->decimal('max_cpm');
            $table->decimal('max_cpc');
            $table->decimal('budget_per_hour');
            $table->dateTime('time_start');
            $table->dateTime('time_end');
        });

        DB::statement("ALTER TABLE network_campaigns MODIFY uuid varbinary(16) NOT NULL");

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
