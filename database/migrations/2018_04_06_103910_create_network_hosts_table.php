<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateNetworkHostsTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('network_hosts', function (Blueprint $table) {
            $table->string('address', 6)->primary(); // binary // REQ CUSTOM ALTER // ESC address
            $table->string('host', 128); // hostname !!

            $table->timestamps();
            $table->softDeletes();

            $table->integer('last_seen')->unsigned(); // TODO: rename last_broadcast
        });

        DB::statement("ALTER TABLE network_hosts MODIFY address varbinary(6)");
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('network_hosts');
    }
}
