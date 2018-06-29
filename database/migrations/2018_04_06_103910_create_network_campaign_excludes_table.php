<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateNetworkCampaignExcludesTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('network_campaign_excludes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->binary('uuid', 16); // REQ CUSTOM ALTER

            $table->timestamps();
            $table->timestamp('source_created_at')->nullable();
            $table->timestamp('source_updated_at')->nullable();

            $table->bigInteger('network_campaign_id')->unsigned();
            $table->binary('name', 64); // REQ CUSTOM ALTER
            $table->binary('min', 64); // REQ CUSTOM ALTER
            $table->binary('max', 64); // REQ CUSTOM ALTER
        });

        if (DB::isMysql()) {
            DB::statement("ALTER TABLE network_campaign_excludes MODIFY uuid varbinary(16) NOT NULL");
            DB::statement("ALTER TABLE network_campaign_excludes MODIFY name varbinary(64)");
            DB::statement("ALTER TABLE network_campaign_excludes MODIFY min varbinary(64)");
            DB::statement("ALTER TABLE network_campaign_excludes MODIFY max varbinary(64)");
        }

        Schema::table('network_campaign_excludes', function (Blueprint $table) {
            $table->unique('uuid');
            $table->index(['network_campaign_id','name','min'], 'network_campaign_excludes_min');
            $table->index(['network_campaign_id','name','max'], 'network_campaign_excludes_max');
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('network_campaign_excludes');
    }
}
