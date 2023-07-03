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

use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Repository\CampaignRepository;
use Adshares\Adserver\Services\Demand\BannerClassificationCreator;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Infrastructure\Service\ExchangeRateReader;
use DateTimeImmutable;

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
        /** @var BannerClassificationCreator $bannerClassificationCreator */
        $bannerClassificationCreator = self::mock(BannerClassificationCreator::class);
        /** @var ExchangeRateReader $exchangeRateReader */
        $exchangeRateReader = self::mock(ExchangeRateReader::class);
        $campaignRepository = new CampaignRepository($bannerClassificationCreator, $exchangeRateReader);

        $campaigns = $campaignRepository->fetchLastCampaignsEndedBefore(new DateTimeImmutable('-2 weeks'));

        self::assertEmpty($campaigns);
    }
}
