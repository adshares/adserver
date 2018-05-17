<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateNetworkBannersTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('network_banners', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->binary('uuid', 16); // REQ CUSTOM ALTER

            $table->timestamps();
            $table->timestamp('source_created_at')->nullable();
            $table->timestamp('source_updated_at')->nullable();

            $table->bigInteger('network_campaign_id')->unsigned();

            $table->string('serve_url', 1024);
            $table->string('click_url', 1024);
            $table->string('view_url', 1024);

            $table->string('creative_type', 32);
            $table->binary('creative_sha1', 20); // REQ CUSTOM ALTER

            $table->integer('creative_width');
            $table->integer('creative_height');
        });

        DB::statement("ALTER TABLE network_banners MODIFY uuid varbinary(16)");
        DB::statement("ALTER TABLE network_banners MODIFY creative_sha1 varbinary(20)");
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('network_banners');
    }
}
