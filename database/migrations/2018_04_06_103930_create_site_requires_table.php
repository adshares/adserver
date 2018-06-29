<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateSiteRequiresTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('site_requires', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->timestamps();
            $table->softDeletes();

            $table->bigInteger('site_id')->unsigned();
            $table->binary('name', 64);
            $table->binary('min', 64);
            $table->binary('max', 64);

            $table->foreign('site_id')->references('id')->on('sites')->onUpdate('RESTRICT')->onDelete('CASCADE');
        });

        if (DB::isMysql()) {
            DB::statement("ALTER TABLE site_requires MODIFY name varbinary(64)");
            DB::statement("ALTER TABLE site_requires MODIFY min varbinary(64)");
            DB::statement("ALTER TABLE site_requires MODIFY max varbinary(64)");
        }

        Schema::table('site_requires', function (Blueprint $table) {
            $table->index(['site_id','name','min'], 'site_requires_min');
            $table->index(['site_id','name','max'], 'site_requires_max');
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('site_requires');
    }
}
