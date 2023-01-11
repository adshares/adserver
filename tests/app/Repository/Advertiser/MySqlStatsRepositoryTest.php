<?php

/**
 * Copyright (c) 2018-2023 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AdServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdServer. If not, see <https://www.gnu.org/licenses/>
 */

declare(strict_types=1);

namespace Adshares\Adserver\Tests\Repository\Advertiser;

use Adshares\Adserver\Exceptions\Advertiser\MissingEventsException;
use Adshares\Adserver\Models\EventLog;
use Adshares\Adserver\Models\Payment;
use Adshares\Adserver\Repository\Advertiser\MySqlStatsRepository as MysqlAdvertiserStatsRepository;
use Adshares\Adserver\Tests\TestCase;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class MySqlStatsRepositoryTest extends TestCase
{
    public function testAggregateStatisticsWhileSingleEvent(): void
    {
        $payment = Payment::factory()->create(['state' => Payment::STATE_SUCCESSFUL]);
        $event = EventLog::factory()->create([
            'domain' => 'example.com',
            'event_type' => EventLog::TYPE_VIEW,
            'event_value_currency' => 285965709,
            'payment_id' => $payment,
        ]);
        $repository = new MysqlAdvertiserStatsRepository();
        $dateTo = new DateTimeImmutable();
        $dateFrom = $dateTo->modify('-5 minutes');

        $repository->aggregateStatistics($dateFrom, $dateTo);

        $hourlyRows = DB::select('SELECT * FROM event_logs_hourly');
        self::assertCount(1, $hourlyRows);
        $hourly = $hourlyRows[0];
        self::assertEquals(285965709, $hourly->cost);
        self::assertEquals(285965709, $hourly->cost_payment);
        self::assertEquals(0, $hourly->clicks);
        self::assertEquals(1, $hourly->views);
        self::assertEquals(0, $hourly->clicks_all);
        self::assertEquals(1, $hourly->views_all);
        self::assertEquals(1, $hourly->views_unique);
        self::assertEquals($event->advertiser_id, bin2hex($hourly->advertiser_id));
        self::assertEquals($event->campaign_id, bin2hex($hourly->campaign_id));
        self::assertEquals($event->banner_id, bin2hex($hourly->banner_id));
        self::assertEquals($event->domain, $hourly->domain);
        $hourlyStatsRows = DB::select('SELECT * FROM event_logs_hourly_stats');
        self::assertCount(2, $hourlyStatsRows);
        $hourlyStatsGroupedByCampaign = array_values(
            array_filter($hourlyStatsRows, fn($row) => null === $row->banner_id)
        )[0];
        self::assertEquals(285965709, $hourlyStatsGroupedByCampaign->cost);
        self::assertEquals(285965709, $hourlyStatsGroupedByCampaign->cost_payment);
        self::assertEquals(0, $hourlyStatsGroupedByCampaign->clicks);
        self::assertEquals(1, $hourlyStatsGroupedByCampaign->views);
        self::assertEquals(0, $hourlyStatsGroupedByCampaign->clicks_all);
        self::assertEquals(1, $hourlyStatsGroupedByCampaign->views_all);
        self::assertEquals(1, $hourlyStatsGroupedByCampaign->views_unique);
        self::assertEquals($event->advertiser_id, bin2hex($hourlyStatsGroupedByCampaign->advertiser_id));
        self::assertEquals($event->campaign_id, bin2hex($hourlyStatsGroupedByCampaign->campaign_id));
        self::assertNull($hourlyStatsGroupedByCampaign->banner_id);
        $hourlyStatsGroupedByBanner = array_values(
            array_filter($hourlyStatsRows, fn($row) => null !== $row->banner_id)
        )[0];
        self::assertEquals(285965709, $hourlyStatsGroupedByBanner->cost);
        self::assertEquals(285965709, $hourlyStatsGroupedByBanner->cost_payment);
        self::assertEquals(0, $hourlyStatsGroupedByBanner->clicks);
        self::assertEquals(1, $hourlyStatsGroupedByBanner->views);
        self::assertEquals(0, $hourlyStatsGroupedByBanner->clicks_all);
        self::assertEquals(1, $hourlyStatsGroupedByBanner->views_all);
        self::assertEquals(1, $hourlyStatsGroupedByBanner->views_unique);
        self::assertEquals($event->advertiser_id, bin2hex($hourly->advertiser_id));
        self::assertEquals($event->campaign_id, bin2hex($hourly->campaign_id));
        self::assertEquals($event->banner_id, bin2hex($hourly->banner_id));
    }

    public function testAggregateStatisticsTwoEventsSameCampaignSameBannerSameDomainDifferentUser(): void
    {
        $payment = Payment::factory()->create(['state' => Payment::STATE_SUCCESSFUL]);

        $advertiserId = $this->randomUuid();
        $campaignId = $this->randomUuid();
        $bannerId = $this->randomUuid();
        $domain = 'e1.com';
        EventLog::factory()->create([
            'advertiser_id' => $advertiserId,
            'campaign_id' => $campaignId,
            'banner_id' => $bannerId,
            'domain' => $domain,
            'event_type' => EventLog::TYPE_VIEW,
            'event_value_currency' => 1000,
            'payment_id' => $payment,
        ]);
        EventLog::factory()->create([
            'advertiser_id' => $advertiserId,
            'campaign_id' => $campaignId,
            'banner_id' => $bannerId,
            'domain' => $domain,
            'event_type' => EventLog::TYPE_VIEW,
            'event_value_currency' => 500,
            'payment_id' => $payment,
        ]);
        $repository = new MysqlAdvertiserStatsRepository();
        $dateTo = new DateTimeImmutable();
        $dateFrom = $dateTo->modify('-5 minutes');

        $repository->aggregateStatistics($dateFrom, $dateTo);

        $hourlyRows = DB::select('SELECT * FROM event_logs_hourly');
        self::assertCount(1, $hourlyRows);
        $hourly = $hourlyRows[0];
        self::assertEquals(1500, $hourly->cost);
        self::assertEquals(1500, $hourly->cost_payment);
        self::assertEquals(0, $hourly->clicks);
        self::assertEquals(2, $hourly->views);
        self::assertEquals(0, $hourly->clicks_all);
        self::assertEquals(2, $hourly->views_all);
        self::assertEquals(2, $hourly->views_unique);
        self::assertEquals($advertiserId, bin2hex($hourly->advertiser_id));
        self::assertEquals($campaignId, bin2hex($hourly->campaign_id));
        self::assertEquals($bannerId, bin2hex($hourly->banner_id));
        self::assertEquals($domain, $hourly->domain);
        $hourlyStatsRows = DB::select('SELECT * FROM event_logs_hourly_stats');
        self::assertCount(2, $hourlyStatsRows);
        $hourlyStatsGroupedByCampaign = array_values(
            array_filter($hourlyStatsRows, fn($row) => null === $row->banner_id)
        )[0];
        self::assertEquals(1500, $hourlyStatsGroupedByCampaign->cost);
        self::assertEquals(1500, $hourlyStatsGroupedByCampaign->cost_payment);
        self::assertEquals(0, $hourlyStatsGroupedByCampaign->clicks);
        self::assertEquals(2, $hourlyStatsGroupedByCampaign->views);
        self::assertEquals(0, $hourlyStatsGroupedByCampaign->clicks_all);
        self::assertEquals(2, $hourlyStatsGroupedByCampaign->views_all);
        self::assertEquals(2, $hourlyStatsGroupedByCampaign->views_unique);
        self::assertEquals($advertiserId, bin2hex($hourlyStatsGroupedByCampaign->advertiser_id));
        self::assertEquals($campaignId, bin2hex($hourlyStatsGroupedByCampaign->campaign_id));
        self::assertNull($hourlyStatsGroupedByCampaign->banner_id);
        $hourlyStatsGroupedByBanner = array_values(
            array_filter($hourlyStatsRows, fn($row) => null !== $row->banner_id)
        )[0];
        self::assertEquals(1500, $hourlyStatsGroupedByBanner->cost);
        self::assertEquals(1500, $hourlyStatsGroupedByBanner->cost_payment);
        self::assertEquals(0, $hourlyStatsGroupedByBanner->clicks);
        self::assertEquals(2, $hourlyStatsGroupedByBanner->views);
        self::assertEquals(0, $hourlyStatsGroupedByBanner->clicks_all);
        self::assertEquals(2, $hourlyStatsGroupedByBanner->views_all);
        self::assertEquals(2, $hourlyStatsGroupedByBanner->views_unique);
        self::assertEquals($advertiserId, bin2hex($hourlyStatsGroupedByCampaign->advertiser_id));
        self::assertEquals($campaignId, bin2hex($hourlyStatsGroupedByCampaign->campaign_id));
        self::assertEquals($bannerId, bin2hex($hourly->banner_id));
    }

    public function testAggregateStatisticsTwoEventsSameCampaignSameBannerSameDomainSameUser(): void
    {
        $payment = Payment::factory()->create(['state' => Payment::STATE_SUCCESSFUL]);

        $advertiserId = $this->randomUuid();
        $campaignId = $this->randomUuid();
        $bannerId = $this->randomUuid();
        $domain = 'e1.com';
        $userId = $this->randomUuid();
        EventLog::factory()->create([
            'advertiser_id' => $advertiserId,
            'campaign_id' => $campaignId,
            'banner_id' => $bannerId,
            'domain' => $domain,
            'event_type' => EventLog::TYPE_VIEW,
            'event_value_currency' => 1000,
            'payment_id' => $payment,
            'user_id' => $userId,
        ]);
        EventLog::factory()->create([
            'advertiser_id' => $advertiserId,
            'campaign_id' => $campaignId,
            'banner_id' => $bannerId,
            'domain' => $domain,
            'event_type' => EventLog::TYPE_VIEW,
            'event_value_currency' => 500,
            'payment_id' => $payment,
            'user_id' => $userId,
        ]);
        $repository = new MysqlAdvertiserStatsRepository();
        $dateTo = new DateTimeImmutable();
        $dateFrom = $dateTo->modify('-5 minutes');

        $repository->aggregateStatistics($dateFrom, $dateTo);

        $hourlyRows = DB::select('SELECT * FROM event_logs_hourly');
        self::assertCount(1, $hourlyRows);
        $hourly = $hourlyRows[0];
        self::assertEquals(1500, $hourly->cost);
        self::assertEquals(1500, $hourly->cost_payment);
        self::assertEquals(0, $hourly->clicks);
        self::assertEquals(2, $hourly->views);
        self::assertEquals(0, $hourly->clicks_all);
        self::assertEquals(2, $hourly->views_all);
        self::assertEquals(1, $hourly->views_unique);
        self::assertEquals($advertiserId, bin2hex($hourly->advertiser_id));
        self::assertEquals($campaignId, bin2hex($hourly->campaign_id));
        self::assertEquals($bannerId, bin2hex($hourly->banner_id));
        self::assertEquals($domain, $hourly->domain);
        $hourlyStatsRows = DB::select('SELECT * FROM event_logs_hourly_stats');
        self::assertCount(2, $hourlyStatsRows);
        $hourlyStatsGroupedByCampaign = array_values(
            array_filter($hourlyStatsRows, fn($row) => null === $row->banner_id)
        )[0];
        self::assertEquals(1500, $hourlyStatsGroupedByCampaign->cost);
        self::assertEquals(1500, $hourlyStatsGroupedByCampaign->cost_payment);
        self::assertEquals(0, $hourlyStatsGroupedByCampaign->clicks);
        self::assertEquals(2, $hourlyStatsGroupedByCampaign->views);
        self::assertEquals(0, $hourlyStatsGroupedByCampaign->clicks_all);
        self::assertEquals(2, $hourlyStatsGroupedByCampaign->views_all);
        self::assertEquals(1, $hourlyStatsGroupedByCampaign->views_unique);
        self::assertEquals($advertiserId, bin2hex($hourlyStatsGroupedByCampaign->advertiser_id));
        self::assertEquals($campaignId, bin2hex($hourlyStatsGroupedByCampaign->campaign_id));
        self::assertNull($hourlyStatsGroupedByCampaign->banner_id);
        $hourlyStatsGroupedByBanner = array_values(
            array_filter($hourlyStatsRows, fn($row) => null !== $row->banner_id)
        )[0];
        self::assertEquals(1500, $hourlyStatsGroupedByBanner->cost);
        self::assertEquals(1500, $hourlyStatsGroupedByBanner->cost_payment);
        self::assertEquals(0, $hourlyStatsGroupedByBanner->clicks);
        self::assertEquals(2, $hourlyStatsGroupedByBanner->views);
        self::assertEquals(0, $hourlyStatsGroupedByBanner->clicks_all);
        self::assertEquals(2, $hourlyStatsGroupedByBanner->views_all);
        self::assertEquals(1, $hourlyStatsGroupedByBanner->views_unique);
        self::assertEquals($advertiserId, bin2hex($hourlyStatsGroupedByCampaign->advertiser_id));
        self::assertEquals($campaignId, bin2hex($hourlyStatsGroupedByCampaign->campaign_id));
        self::assertEquals($bannerId, bin2hex($hourly->banner_id));
    }

    public function testAggregateStatisticsWhileNoEvents(): void
    {
        $repository = new MysqlAdvertiserStatsRepository();
        $dateTo = new DateTimeImmutable();
        $dateFrom = $dateTo->modify('-5 minutes');

        self::expectException(MissingEventsException::class);

        $repository->aggregateStatistics($dateFrom, $dateTo);
    }

    private function randomUuid(): string
    {
        return str_replace('-', '', $this->faker->uuid);
    }
}
