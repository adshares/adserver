<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateNetworkPaymentsTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('network_payments', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->timestamps();
            $table->softDeletes();

            $table->binary('receiver_address', 6); // REQ CUSTOM ALTER
            $table->binary('sender_address', 6); // REQ CUSTOM ALTER
            $table->string('sender_host', 32);
            $table->decimal('amount');
            $table->integer('create_time');
            $table->string('tx_id', 128);
            $table->integer('tx_time');
            $table->boolean('detailed_data_used');
            $table->boolean('processed');
        });

        DB::statement("ALTER TABLE network_payments MODIFY receiver_address varbinary(6)");
        DB::statement("ALTER TABLE network_payments MODIFY sender_address varbinary(6)");
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('network_payments');
    }
}
