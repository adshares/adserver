<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreatePaymentsTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->timestamps();
            $table->softDeletes();

            $table->text('transfers');
            $table->text('subthreshold_transfers')->nullable();
            $table->binary('account_address', 6)->nullable(); // REQ CUSTOM ALTER
            $table->binary('account_hashin', 32)->nullable(); // REQ CUSTOM ALTER
            $table->binary('account_hashout', 32)->nullable(); // REQ CUSTOM ALTER
            $table->integer('account_msid');
            $table->text('tx_data');
            $table->binary('tx_id', 8); // REQ CUSTOM ALTER
            $table->integer('tx_time');
            $table->decimal('fee', 20, 9);
            $table->boolean('completed');
        });

        if (DB::isMysql()) {
            DB::statement("ALTER TABLE payments MODIFY account_address varbinary(6)");
            DB::statement("ALTER TABLE payments MODIFY account_hashin varbinary(32)");
            DB::statement("ALTER TABLE payments MODIFY account_hashout varbinary(32)");
            DB::statement("ALTER TABLE payments MODIFY tx_id varbinary(6)");
        }
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('payments');
    }
}
