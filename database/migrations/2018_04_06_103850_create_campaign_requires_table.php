<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateCampaignRequiresTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('campaign_requires', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->binary('uuid', 16); // REQ CUSTOM ALTER

            $table->timestamps();
            $table->softDeletes();

            $table->bigInteger('campaign_id')->unsigned();

            $table->binary('name', 64); // REQ CUSTOM ALTER
            $table->binary('min', 64); // REQ CUSTOM ALTER
            $table->binary('max', 64); // REQ CUSTOM ALTER

            $table->foreign('campaign_id')->references('id')->on('campaigns')->onUpdate('RESTRICT')->onDelete('CASCADE');
        });

        if (DB::isMysql()) {
            DB::statement('ALTER TABLE campaign_requires MODIFY uuid varbinary(16) NOT NULL');
            DB::statement('ALTER TABLE campaign_requires MODIFY name varbinary(64)');
            DB::statement('ALTER TABLE campaign_requires MODIFY min varbinary(64)');
            DB::statement('ALTER TABLE campaign_requires MODIFY max varbinary(64)');
        }

        Schema::table('campaign_requires', function (Blueprint $table) {
            $table->unique('uuid');
            $table->index(['campaign_id', 'name', 'min'], 'campaign_requires_min');
            $table->index(['campaign_id', 'name', 'max'], 'campaign_requires_max');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::drop('campaign_requires');
    }
}
