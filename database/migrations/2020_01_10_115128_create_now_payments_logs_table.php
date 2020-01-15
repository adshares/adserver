<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateNowPaymentsLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'now_payments_logs',
            function (Blueprint $table) {
                $table->increments('id');
                $table->bigInteger('user_id')->index();
                $table->string('order_id', 24)->index();
                $table->string('status', 16);
                $table->decimal('amount', 14, 8);
                $table->string('currency', 16);
                $table->string('payment_id', 16)->nullable()->index();
                $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
                $table->json('context');
            }
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('now_payments_logs');
    }
}
