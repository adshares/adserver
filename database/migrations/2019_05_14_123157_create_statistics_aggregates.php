<?php

use Adshares\Adserver\Facades\DB;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStatisticsAggregates extends Migration
{
    private const TABLE_EVENT_LOGS = 'event_logs_hourly';

    private const TABLE_NETWORK_EVENT_LOGS = 'network_event_logs_hourly';

    public function up(): void
    {
        Schema::create(
            self::TABLE_EVENT_LOGS,
            function (Blueprint $table) {
                $table->increments('id');
                $table->timestamp('hour_timestamp')->nullable(false);

                $table->binary('advertiser_id')->nullable(false);
                $table->binary('campaign_id')->nullable(false);
                $table->binary('banner_id')->nullable(false);
                $table->string('domain', 255)->nullable(false);

                $table->bigInteger('cost')->nullable(false);
                $table->unsignedInteger('clicks')->nullable(false);
                $table->unsignedInteger('views')->nullable(false);
                $table->unsignedInteger('clicks_all')->nullable(false);
                $table->unsignedInteger('views_all')->nullable(false);
                $table->unsignedInteger('views_unique')->nullable(false);
            }
        );

        Schema::create(
            self::TABLE_NETWORK_EVENT_LOGS,
            function (Blueprint $table) {
                $table->increments('id');
                $table->timestamp('hour_timestamp')->nullable(false);

                $table->binary('publisher_id')->nullable(false);
                $table->binary('site_id')->nullable(false);
                $table->binary('zone_id')->nullable(false);
                $table->string('domain', 255)->nullable(false);

                $table->bigInteger('revenue')->nullable(false);
                $table->unsignedInteger('clicks')->nullable(false);
                $table->unsignedInteger('views')->nullable(false);
                $table->unsignedInteger('clicks_all')->nullable(false);
                $table->unsignedInteger('views_all')->nullable(false);
                $table->unsignedInteger('views_unique')->nullable(false);
            }
        );

        if (DB::isMySql()) {
            DB::statement(sprintf('ALTER TABLE %s MODIFY advertiser_id varbinary(16)', self::TABLE_EVENT_LOGS));
            DB::statement(sprintf('ALTER TABLE %s MODIFY campaign_id varbinary(16)', self::TABLE_EVENT_LOGS));
            DB::statement(sprintf('ALTER TABLE %s MODIFY banner_id varbinary(16)', self::TABLE_EVENT_LOGS));

            DB::statement(sprintf('ALTER TABLE %s MODIFY publisher_id varbinary(16)', self::TABLE_NETWORK_EVENT_LOGS));
            DB::statement(sprintf('ALTER TABLE %s MODIFY site_id varbinary(16)', self::TABLE_NETWORK_EVENT_LOGS));
            DB::statement(sprintf('ALTER TABLE %s MODIFY zone_id varbinary(16)', self::TABLE_NETWORK_EVENT_LOGS));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(self::TABLE_EVENT_LOGS);
        Schema::dropIfExists(self::TABLE_NETWORK_EVENT_LOGS);
    }
}
