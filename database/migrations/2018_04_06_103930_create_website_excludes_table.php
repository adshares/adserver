<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateWebsiteExcludesTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('website_excludes', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->timestamps();
            $table->softDeletes();

            $table->bigInteger('website_id')->unsigned();
            $table->binary('name', 64);
            $table->binary('min', 64);
            $table->binary('max', 64);
        });

        DB::statement("ALTER TABLE website_excludes MODIFY name varbinary(64)");
        DB::statement("ALTER TABLE website_excludes MODIFY min varbinary(64)");
        DB::statement("ALTER TABLE website_excludes MODIFY max varbinary(64)");

        Schema::table('website_excludes', function (Blueprint $table) {
            $table->index(['website_id','name','min'], 'min');
            $table->index(['website_id','name','max'], 'max');
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('website_excludes');
    }
}
