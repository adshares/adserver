<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUsersAdserversWallets extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('users_adserver_wallets', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->timestamps();

            $table->bigInteger('user_id')->unsigned()->unique();

            $table->binary('adshares_address', 6)->nullable();
            $table->string('payment_memo', 48)->nullable();

            $table->decimal('total_funds', 20, 9)->default(0);
            $table->decimal('total_funds_in_currency', 20, 2)->default(0);
            $table->decimal('total_funds_change', 20, 9)->default(0);

            $table->timestamp('last_payment_at')->nullable();

            $table->foreign('user_id')->references('id')->on('users')->onUpdate('RESTRICT')->onDelete('CASCADE');
        });

        if (DB::isMysql()) {
            DB::statement('ALTER TABLE users_adserver_wallets MODIFY adshares_address varbinary(6)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('users_adserver_wallets');
    }
}
