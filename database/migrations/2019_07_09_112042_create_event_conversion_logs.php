<?php

use Adshares\Adserver\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;

class CreateEventConversionLogs extends Migration
{
    public function up(): void 
    {
        DB::statement('CREATE TABLE event_conversion_logs LIKE event_logs');
    }

    public function down(): void
    {
        Schema::dropIfExists('event_conversion_logs');
    }
}
