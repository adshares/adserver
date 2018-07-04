<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->binary('uuid', 16);
            $table->string('email', 191)->unique();
            $table->string('email_confirm_token', 32)->nullable();
            $table->timestamp('email_confirmed_at')->nullable();

            $table->rememberToken();

            $table->timestamps();
            $table->softDeletes();

            $table->string('password');

            $table->string('name')->nullable();

            $table->boolean('is_advertiser')->nullable();
            $table->boolean('is_publisher')->nullable();
            $table->boolean('is_admin')->default(false);
        });

        if (DB::isMysql()) {
            DB::statement('ALTER TABLE users MODIFY uuid varbinary(16) NOT NULL');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unique('uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
}
