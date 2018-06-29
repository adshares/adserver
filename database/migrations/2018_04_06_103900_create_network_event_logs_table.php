<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateNetworkEventLogsTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('network_event_logs', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->timestamps();

            $table->binary('cid', 16); // REQ CUSTOM ALTER
            $table->binary('tid', 16); // REQ CUSTOM ALTER

            $table->binary('banner_id', 16); // REQ CUSTOM ALTER
            $table->string('event_type', 16);

            $table->binary('pay_from', 6); // REQ CUSTOM ALTER

            $table->binary('ip', 8); // REQ CUSTOM ALTER

            $table->text('context')->nullable();

            $table->binary('user_id', 16)->nullable(); // REQ CUSTOM ALTER
            $table->integer('human_score')->nullable();
            $table->text('our_userdata')->nullable();
            $table->text('their_userdata')->nullable();
            $table->decimal('event_value', 20, 9)->nullable();
            $table->decimal('paid_amount', 20, 9)->nullable();
            $table->integer('payment_id')->nullable();
        });

        if (DB::isMysql()) {
            DB::statement("ALTER TABLE network_event_logs MODIFY cid varbinary(16)");
            DB::statement("ALTER TABLE network_event_logs MODIFY tid varbinary(16)");
            DB::statement("ALTER TABLE network_event_logs MODIFY banner_id varbinary(16)");
            DB::statement("ALTER TABLE network_event_logs MODIFY pay_from varbinary(6)");
            DB::statement("ALTER TABLE network_event_logs MODIFY ip varbinary(8)");
            DB::statement("ALTER TABLE network_event_logs MODIFY user_id varbinary(16)");
        }
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('network_event_logs');
    }
}
