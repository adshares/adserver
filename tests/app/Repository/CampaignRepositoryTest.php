<?php

/**
 * Copyright (c) 2018-2022 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Tests\Repository;

use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\NotificationEmailLog;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\Repository\CampaignRepository;
use Adshares\Adserver\Services\Demand\BannerClassificationCreator;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\ViewModel\NotificationEmailCategory;
use Adshares\Common\Infrastructure\Service\ExchangeRateReader;
use Adshares\Mock\Client\DummyExchangeRateRepository;
use DateTimeImmutable;
use Illuminate\Support\Carbon;

final class CampaignRepositoryTest extends TestCase
{
    public function testFetchLastCampaignsEndedBeforeWhileAnotherIndeterminate(): void
    {
        $user = User::factory()->create();
        Campaign::factory()->create(
            [
                'status' => Campaign::STATUS_ACTIVE,
                'time_start' => (new DateTimeImmutable('-1 month'))->format(DATE_ATOM),
                'time_end' => (new DateTimeImmutable('-20 days'))->format(DATE_ATOM),
                'user_id' => $user,
            ]
        );
        Campaign::factory()->create(
            [
                'status' => Campaign::STATUS_ACTIVE,
                'time_start' => (new DateTimeImmutable('-1 month'))->format(DATE_ATOM),
                'time_end' => null,
                'user_id' => $user,
            ]
        );
        $campaignRepository = new CampaignRepository(
            $this->getBannerClassificationCreator(),
            $this->getExchangeRateReader(),
        );

        $campaigns = $campaignRepository->fetchLastCampaignsEndedBefore(new DateTimeImmutable('-2 weeks'));

        self::assertEmpty($campaigns);
    }

    public function testUpdateAddBanner(): void
    {
        $user = User::factory()->create();
        UserLedgerEntry::factory()->create(
            [
                'amount' => 50_000 * 1e11,
                'status' => UserLedgerEntry::STATUS_ACCEPTED,
                'type' => UserLedgerEntry::TYPE_DEPOSIT,
                'user_id' => $user,
            ]
        );
        /** @var Campaign $campaign */
        $campaign = Campaign::factory()->create(
            [
                'status' => Campaign::STATUS_ACTIVE,
                'time_start' => (new DateTimeImmutable('-1 month'))->format(DATE_ATOM),
                'user_id' => $user,
            ]
        );
        Banner::factory()->create(['campaign_id' => $campaign]);
        /** @var NotificationEmailLog $notificationLogEntry */
        $notificationLogEntry = NotificationEmailLog::factory()->create(
            [
                'category' => NotificationEmailCategory::CampaignAccepted,
                'properties' => ['campaignId' => $campaign->id],
                'user_id' => $user,
            ]
        );
        $campaignRepository = new CampaignRepository(
            $this->getBannerClassificationCreator(),
            $this->getExchangeRateReader(),
        );
        $banners = [Banner::factory()->make()];

        $campaignRepository->update($campaign, $banners);

        self::assertCount(2, $campaign->banners);
        self::assertLessThanOrEqual(Carbon::now(), $notificationLogEntry->refresh()->valid_until);
    }

    private function getExchangeRateReader(): ExchangeRateReader
    {
        /** @var ExchangeRateReader $exchangeRateReader */
        $exchangeRateReader = self::createMock(ExchangeRateReader::class);
        $exchangeRateReader->method('fetchExchangeRate')
            ->willReturn((new DummyExchangeRateRepository())->fetchExchangeRate());
        return $exchangeRateReader;
    }

    private function getBannerClassificationCreator(): BannerClassificationCreator
    {
        /** @var BannerClassificationCreator $bannerClassificationCreator */
        $bannerClassificationCreator = self::createMock(BannerClassificationCreator::class);
        return $bannerClassificationCreator;
    }
}
