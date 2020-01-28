<?php
/**
 * Copyright (c) 2018-2019 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License,
 * or (at your option) any later version.
 *
 * AdServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdServer. If not, see <https://www.gnu.org/licenses/>
 */

use Adshares\Adserver\Facades\DB;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class SplitNetworkEventLogs extends Migration
{
    private const TABLE_NETWORK_IMPRESSIONS = 'network_impressions';

    private const TABLE_NETWORK_CASES = 'network_cases';

    private const TABLE_NETWORK_CASE_PAYMENTS = 'network_case_payments';

    private const TABLE_NETWORK_CASE_CLICKS = 'network_case_clicks';

    private const TABLE_NETWORK_CASE_LOGS_HOURLY = 'network_case_logs_hourly';

    public function up(): void
    {
        $this->createTableNetworkImpressions();
        $this->createTableNetworkCases();
        $this->createTableNetworkCasePayments();
        $this->createTableNetworkCaseClicks();
        $this->createTableNetworkCaseLogsHourly();

        $this->insertNetworkImpressions();
        $this->insertNetworkCases();
        $this->insertNetworkCasePayments();
        $this->insertNetworkCaseClicks();
        $this->insertNetworkCaseLogsHourly();
    }

    public function down(): void
    {
        Schema::drop(self::TABLE_NETWORK_CASE_LOGS_HOURLY);
        Schema::drop(self::TABLE_NETWORK_CASE_CLICKS);
        Schema::drop(self::TABLE_NETWORK_CASE_PAYMENTS);
        Schema::drop(self::TABLE_NETWORK_CASES);
        Schema::drop(self::TABLE_NETWORK_IMPRESSIONS);
    }

    private function createTableNetworkImpressions(): void
    {
        Schema::create(
            self::TABLE_NETWORK_IMPRESSIONS,
            function (Blueprint $table) {
                $table->bigIncrements('id');

                $table->timestamps();

                $table->binary('impression_id');
                $table->binary('tracking_id');
                $table->binary('user_id')->nullable();

                $table->text('context');

                $table->decimal('human_score', 3, 2)->nullable();
                $table->text('user_data')->nullable();
            }
        );

        if (DB::isMySql()) {
            DB::statement('ALTER TABLE network_impressions MODIFY impression_id VARBINARY(16) NOT NULL');
            DB::statement('ALTER TABLE network_impressions MODIFY tracking_id VARBINARY(16) NOT NULL');
            DB::statement('ALTER TABLE network_impressions MODIFY user_id VARBINARY(16)');
        }

        Schema::table(
            self::TABLE_NETWORK_IMPRESSIONS,
            function (Blueprint $table) {
                $table->unique('impression_id');
                $table->index('created_at');
            }
        );
    }

    private function createTableNetworkCases(): void
    {
        Schema::create(
            self::TABLE_NETWORK_CASES,
            function (Blueprint $table) {
                $table->bigIncrements('id');

                $table->timestamps();

                $table->binary('case_id');
                $table->unsignedBigInteger('network_impression_id');

                $table->binary('publisher_id');
                $table->binary('site_id');
                $table->binary('zone_id');
                $table->string('domain', 255);
                $table->binary('campaign_id');
                $table->binary('banner_id');
            }
        );

        if (DB::isMySql()) {
            DB::statement('ALTER TABLE network_cases MODIFY case_id VARBINARY(16) NOT NULL');
            DB::statement('ALTER TABLE network_cases MODIFY publisher_id VARBINARY(16) NOT NULL');
            DB::statement('ALTER TABLE network_cases MODIFY site_id VARBINARY(16) NOT NULL');
            DB::statement('ALTER TABLE network_cases MODIFY zone_id VARBINARY(16) NOT NULL');
            DB::statement('ALTER TABLE network_cases MODIFY campaign_id VARBINARY(16) NOT NULL');
            DB::statement('ALTER TABLE network_cases MODIFY banner_id VARBINARY(16) NOT NULL');
        }

        Schema::table(
            self::TABLE_NETWORK_CASES,
            function (Blueprint $table) {
                $table->unique('case_id');
                $table->index('created_at');
            }
        );
    }

    private function createTableNetworkCasePayments(): void
    {
        Schema::create(
            self::TABLE_NETWORK_CASE_PAYMENTS,
            function (Blueprint $table) {
                $table->bigIncrements('id');

                $table->unsignedBigInteger('network_case_id')->index();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('pay_time')->useCurrent()->index();
                $table->bigInteger('ads_payment_id')->index();

                $table->unsignedBigInteger('total_amount');
                $table->unsignedBigInteger('license_fee');
                $table->unsignedBigInteger('operator_fee');
                $table->unsignedBigInteger('paid_amount');
                $table->decimal('exchange_rate', 9, 5);
                $table->unsignedBigInteger('paid_amount_currency');
            }
        );
    }

    private function createTableNetworkCaseClicks(): void
    {
        Schema::create(
            self::TABLE_NETWORK_CASE_CLICKS,
            function (Blueprint $table) {
                $table->bigIncrements('id');

                $table->unsignedBigInteger('network_case_id')->unique();
                $table->timestamp('created_at')->useCurrent()->index();
            }
        );
    }

    private function createTableNetworkCaseLogsHourly(): void
    {
        Schema::create(
            self::TABLE_NETWORK_CASE_LOGS_HOURLY,
            function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->timestamp('hour_timestamp')->useCurrent();
                $table->binary('publisher_id');
                $table->binary('site_id');
                $table->binary('zone_id');
                $table->string('domain', 255);

                $table->bigInteger('revenue_case')->default(0);
                $table->bigInteger('revenue_hour')->default(0);
                $table->unsignedInteger('views_all')->default(0);
                $table->unsignedInteger('views')->default(0);
                $table->unsignedInteger('views_unique')->default(0);
                $table->unsignedInteger('clicks_all')->default(0);
                $table->unsignedInteger('clicks')->default(0);
            }
        );

        if (DB::isMySql()) {
            DB::statement('ALTER TABLE network_case_logs_hourly MODIFY publisher_id VARBINARY(16) NOT NULL');
            DB::statement('ALTER TABLE network_case_logs_hourly MODIFY site_id VARBINARY(16) NOT NULL');
            DB::statement('ALTER TABLE network_case_logs_hourly MODIFY zone_id VARBINARY(16) NOT NULL');
        }

        Schema::table(
            self::TABLE_NETWORK_CASE_LOGS_HOURLY,
            function (Blueprint $table) {
                $table->unique(
                    [
                        'hour_timestamp',
                        'publisher_id',
                        'site_id',
                        'zone_id',
                        'domain',
                    ],
                    'network_case_logs_hourly_index'
                );
            }
        );
    }

    private function insertNetworkImpressions(): void
    {
        DB::insert(
            <<<SQL
INSERT INTO network_impressions (created_at,
                                 updated_at,
                                 impression_id,
                                 tracking_id,
                                 user_id,
                                 context,
                                 human_score,
                                 user_data)
SELECT
  created_at,
  updated_at,
  event_id,
  tracking_id,
  user_id,
  context,
  human_score,
  our_userdata
FROM network_event_logs
WHERE event_type = 'view';
SQL
        );
    }

    private function insertNetworkCases(): void
    {
        DB::insert(
            <<<SQL
INSERT INTO network_cases (created_at,
                           updated_at,
                           case_id,
                           network_impression_id,
                           publisher_id,
                           site_id,
                           zone_id,
                           domain,
                           campaign_id,
                           banner_id)
SELECT
  e.created_at,
  e.updated_at,
  e.case_id,
  i.id,
  e.publisher_id,
  e.site_id,
  e.zone_id,
  e.domain,
  e.campaign_id,
  e.banner_id
FROM network_event_logs e
      JOIN network_impressions i ON e.event_id = i.impression_id
WHERE e.event_type = 'view';
SQL
        );
    }

    private function insertNetworkCasePayments(): void
    {
        DB::insert(
            <<<SQL
INSERT INTO network_case_payments (network_case_id,
                                   ads_payment_id,
                                   pay_time,
                                   total_amount,
                                   license_fee,
                                   operator_fee,
                                   paid_amount,
                                   exchange_rate,
                                   paid_amount_currency)
SELECT
  c.id,
  ads_payment_id,
  (SELECT created_at FROM ads_payments WHERE id = ads_payment_id),
  SUM(event_value),
  SUM(license_fee),
  SUM(operator_fee),
  SUM(paid_amount),
  AVG(exchange_rate),
  SUM(paid_amount_currency)
FROM network_event_logs e
       JOIN network_cases c ON e.case_id = c.case_id
WHERE ads_payment_id IS NOT NULL
GROUP BY 1, 2;
SQL
        );
    }

    private function insertNetworkCaseClicks(): void
    {
        DB::insert(
            <<<SQL
INSERT INTO network_case_clicks (network_case_id,
                                 created_at)
SELECT
  c.id,
  e.created_at
FROM network_event_logs e
       JOIN network_cases c ON e.case_id = c.case_id
WHERE e.event_type = 'click';
SQL
        );
    }

    private function insertNetworkCaseLogsHourly(): void
    {
        DB::insert(
            <<<SQL
INSERT INTO network_case_logs_hourly(hour_timestamp,
                                     publisher_id,
                                     site_id,
                                     zone_id,
                                     domain,
                                     revenue_case,
                                     revenue_hour,
                                     views_all,
                                     views,
                                     views_unique,
                                     clicks_all,
                                     clicks)
SELECT
  hour_timestamp,
  publisher_id,
  site_id,
  zone_id,
  domain,
  revenue,
  revenue,
  views_all,
  views,
  views_unique,
  clicks_all,
  clicks
FROM network_event_logs_hourly;
SQL
        );
    }
}
