<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateBannersTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
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
        });

        DB::statement("ALTER TABLE banners MODIFY creative_contents MEDIUMBLOB");
        DB::statement("ALTER TABLE banners MODIFY uuid varbinary(16) NOT NULL");
        DB::statement("ALTER TABLE banners MODIFY creative_sha1 varbinary(20)");
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('banners');
    }
}
