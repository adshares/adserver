<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateNotificationsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->bigincrements('id');
            $table->timestamps();
            $table->softDeletes();

            $table->timestamp('seen_at')->nullable();

            $table->bigInteger('user_id')->unsigned();
            $table->string('user_role', 10)->default('all');
            $table->string('type', 12);

            $table->string('title', 96);
            $table->string('message');

            $table->text('payload')->nullable();

            $table->index('user_role');
            $table->index('type');
            $table->foreign('user_id')->references('id')->on('users')->onUpdate('RESTRICT')->onDelete('CASCADE');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('notifications');
    }
}
