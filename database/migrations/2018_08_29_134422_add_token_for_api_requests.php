<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTokenForApiRequests extends Migration
{
    public function up()
    {
        Schema::table('users',
            function (Blueprint $table) {
                $table->string('api_token', 60)
                    ->unique()
                    ->nullable()
                    ->default(NULL)
                ;
            });
    }

    public function down()
    {
        Schema::table('users',
            function (Blueprint $table) {
                $table->removeColumn('api_token');
            });
    }
}
