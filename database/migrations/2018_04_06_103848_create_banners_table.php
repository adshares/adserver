<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBannersTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {  // TODO  => creatives
        Schema::create('banners', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->binary('uuid', 16); // REQ CUSTOM ALTER

            $table->timestamps();
            $table->softDeletes();

            $table->bigInteger('campaign_id')->unsigned();

            $table->binary('creative_contents', 16777215); // REQ CUSTOM ALTER

            $table->string('creative_type', 32);
            $table->binary('creative_sha1', 20); // REQ CUSTOM ALTER

            $table->integer('creative_width');
            $table->integer('creative_height');

            $table->foreign('campaign_id')->references('id')->on('campaigns')->onUpdate('RESTRICT')->onDelete('CASCADE');
        });

        if (DB::isMysql()) {
            DB::statement('ALTER TABLE banners MODIFY creative_contents MEDIUMBLOB');
            DB::statement('ALTER TABLE banners MODIFY uuid varbinary(16) NOT NULL');
            DB::statement('ALTER TABLE banners MODIFY creative_sha1 varbinary(20)');
        }

        Schema::table('banners', function (Blueprint $table) {
            $table->unique('uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::drop('banners');
    }
}
