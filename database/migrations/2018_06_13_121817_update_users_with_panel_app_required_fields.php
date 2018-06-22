<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateUsersWithPanelAppRequiredFields extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // FIXME default or nullable
            $table->binary('uuid', 16)->nullable()->after('id'); // REQ CUSTOM ALTER

            $table->boolean('isAdvertiser')->nullable();
            $table->boolean('isPublisher')->nullable();
            $table->boolean('isAdmin')->default(false);

            $table->timestamp('email_confirmed_at')->nullable()->after('email');

            $table->string('name')->nullable()->change();
        });

        if (DB::isMysql()) {
            DB::statement("ALTER TABLE users MODIFY uuid varbinary(16) NOT NULL");
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unique('uuid');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['uuid','isAdvertiser','isPublisher','isAdmin','email_confirmed_at']);
        });
    }
}
