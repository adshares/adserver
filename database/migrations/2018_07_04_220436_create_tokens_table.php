<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTokensTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('tokens', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->binary('uuid', 16);
            $table->bigInteger('user_id')->unsigned()->nullable();
            $table->timestamps();
            $table->timestamp('valid_until')->nullable();

            $table->string('tag', 24);
            $table->boolean('multi_usage')->default(false);
            $table->longText('payload')->nullable();

            $table->index('tag');
            $table->foreign('user_id')->references('id')->on('users')->onUpdate('RESTRICT')->onDelete('CASCADE');
        });

        if (DB::isMysql()) {
            DB::statement('ALTER TABLE tokens MODIFY uuid varbinary(16) NOT NULL');
        }

        Schema::table('tokens', function (Blueprint $table) {
            $table->unique('uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('tokens');
    }
}
