<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateConversionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('conversions', static function (Blueprint $table) {
            $table->increments('id');
            $table->bigInteger('campaign_id')->unsigned();
            $table->string('name', 255);
            $table->string('budget_type', 20); // in_budget, out_of_budget
            $table->string('event_type', 50); // e.g. register, click, buy
            $table->string('type')->default('basic'); // basic, advanced
            $table->bigInteger('value')->nullable();
            $table->bigInteger('limit')->nullable();
            $table->timestamps();

            $table->foreign('campaign_id')->references('id')->on('campaigns')->onUpdate('RESTRICT')->onDelete('CASCADE');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('conversion');
    }
}
