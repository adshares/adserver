<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateSiteExcludesTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('site_excludes', function (Blueprint $table) {
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
            DB::statement("ALTER TABLE site_excludes MODIFY name varbinary(64)");
            DB::statement("ALTER TABLE site_excludes MODIFY min varbinary(64)");
            DB::statement("ALTER TABLE site_excludes MODIFY max varbinary(64)");
        }

        Schema::table('site_excludes', function (Blueprint $table) {
            $table->index(['site_id','name','min'], 'site_excludes_min');
            $table->index(['site_id','name','max'], 'site_excludes_max');
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('site_excludes');
    }
}
