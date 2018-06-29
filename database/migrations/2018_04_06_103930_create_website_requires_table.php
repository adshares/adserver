<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateWebsiteRequiresTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('website_requires', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->timestamps();
            $table->softDeletes();

            $table->bigInteger('website_id')->unsigned();
            $table->binary('name', 64);
            $table->binary('min', 64);
            $table->binary('max', 64);
        });

        if (DB::isMysql()) {
            DB::statement("ALTER TABLE website_requires MODIFY name varbinary(64)");
            DB::statement("ALTER TABLE website_requires MODIFY min varbinary(64)");
            DB::statement("ALTER TABLE website_requires MODIFY max varbinary(64)");
        }

        Schema::table('website_requires', function (Blueprint $table) {
            $table->index(['website_id','name','min'], 'website_requires_min');
            $table->index(['website_id','name','max'], 'website_requires_max');
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('website_requires');
    }
}
