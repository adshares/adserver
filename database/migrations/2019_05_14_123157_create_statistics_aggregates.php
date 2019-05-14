<?php

use Adshares\Adserver\Facades\DB;
use Adshares\Adserver\Utilities\DateUtils;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
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
                $table->unsignedInteger('clicksAll')->nullable(false);
                $table->unsignedInteger('viewsAll')->nullable(false);
                $table->unsignedInteger('viewsUnique')->nullable(false);
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
                $table->unsignedInteger('clicksAll')->nullable(false);
                $table->unsignedInteger('viewsAll')->nullable(false);
                $table->unsignedInteger('viewsUnique')->nullable(false);
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

        $this->aggregateLegacyEventsInLoop('event_logs', '-A');
        $this->aggregateLegacyEventsInLoop('network_event_logs', '-P');
    }

    public function down(): void
    {
        Schema::dropIfExists(self::TABLE_EVENT_LOGS);
        Schema::dropIfExists(self::TABLE_NETWORK_EVENT_LOGS);
    }

    private function aggregateLegacyEventsInLoop(string $tableName, string $commandSwitch): void
    {
        $result = DB::select(sprintf('SELECT created_at FROM %s ORDER BY created_at ASC LIMIT 1', $tableName));
        if (count($result) > 0) {
            $dt = DateUtils::getDateTimeRoundedToCurrentHour(
                DateTime::createFromFormat('Y-m-d H:i:s', $result[0]->created_at)
            );

            $now = DateUtils::getDateTimeRoundedToCurrentHour();

            while ($dt < $now) {
                Artisan::call('ops:stats:aggregate', [$commandSwitch => true, '--hour' => $dt->format(DateTime::ATOM)]);
                $dt->modify('+1 hour');
            }
        }
    }
}
