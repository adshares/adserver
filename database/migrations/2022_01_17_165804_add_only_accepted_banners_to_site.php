<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOnlyAcceptedBannersToSite extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn('require_classified');
            $table->dropColumn('exclude_unclassified');
            $table->boolean('only_accepted_banners')->default('0');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn('only_accepted_banners');
            $table->boolean('require_classified')->default('0');
            $table->boolean('exclude_unclassified')->default('0');
        });
    }
}
