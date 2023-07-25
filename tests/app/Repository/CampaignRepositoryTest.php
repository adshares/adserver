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
use Adshares\Adserver\Models\ConversionDefinition;
use Adshares\Adserver\Models\NotificationEmailLog;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\Repository\CampaignRepository;
use Adshares\Adserver\Services\Demand\BannerClassificationCreator;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\ViewModel\NotificationEmailCategory;
use Adshares\Common\Application\Service\Exception\ExchangeRateNotAvailableException;
use Adshares\Common\Exception\InvalidArgumentException;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Common\Infrastructure\Service\ExchangeRateReader;
use Adshares\Mock\Client\DummyExchangeRateRepository;
use DateTimeImmutable;
use Illuminate\Support\Carbon;
use PDOException;

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

    public function testSave(): void
    {
        $user = User::factory()->create();
        $this->addDeposit($user);
        /** @var Campaign $campaign */
        $campaign = Campaign::factory()->make(
            [
                'status' => Campaign::STATUS_ACTIVE,
                'time_start' => (new DateTimeImmutable('-1 month'))->format(DATE_ATOM),
                'user_id' => $user,
            ]
        );
        $banners = [Banner::factory()->make(['campaign_id' => null])];
        $conversions = [ConversionDefinition::factory()->make(['campaign_id' => null])];
        $campaignRepository = new CampaignRepository(
            $this->getBannerClassificationCreator(),
            $this->getExchangeRateReader(),
        );

        $savedCampaign = $campaignRepository->save($campaign, $banners, $conversions);

        self::assertDatabaseCount(Campaign::class, 1);
        self::assertDatabaseCount(Banner::class, 1);
        self::assertDatabaseCount(ConversionDefinition::class, 1);
        self::assertCount(1, $savedCampaign->banners);
        self::assertCount(1, $savedCampaign->conversions);
    }

    public function testSaveFailWhileInsufficientFunds(): void
    {
        $user = User::factory()->create();
        /** @var Campaign $campaign */
        $campaign = Campaign::factory()->make(
            [
                'status' => Campaign::STATUS_ACTIVE,
                'time_start' => (new DateTimeImmutable('-1 month'))->format(DATE_ATOM),
                'user_id' => $user,
            ]
        );
        $banners = [Banner::factory()->make(['campaign_id' => null])];
        $campaignRepository = new CampaignRepository(
            $this->getBannerClassificationCreator(),
            $this->getExchangeRateReader(),
        );

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('Insufficient funds');

        $campaignRepository->save($campaign, $banners);

        self::assertDatabaseEmpty(Campaign::class);
        self::assertDatabaseEmpty(Banner::class);
    }

    public function testSaveFailOnDbError(): void
    {
        $user = User::factory()->create();
        $this->addDeposit($user);
        /** @var Campaign $campaign */
        $campaign = Campaign::factory()->make(
            [
                'status' => Campaign::STATUS_ACTIVE,
                'time_start' => (new DateTimeImmutable('-1 month'))->format(DATE_ATOM),
                'user_id' => $user,
            ]
        );
        $banners = [Banner::factory()->make(['campaign_id' => null])];
        /** @var BannerClassificationCreator $bannerClassificationCreator */
        $bannerClassificationCreator = self::createMock(BannerClassificationCreator::class);
        $bannerClassificationCreator->method('createForCampaign')
            ->willThrowException(new PDOException('test-exception'));
        $campaignRepository = new CampaignRepository($bannerClassificationCreator, $this->getExchangeRateReader());

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('Campaign save failed');

        $campaignRepository->save($campaign, $banners);

        self::assertDatabaseEmpty(Campaign::class);
        self::assertDatabaseEmpty(Banner::class);
    }

    public function testSaveFailOnExchangeRateReaderException(): void
    {
        $user = User::factory()->create();
        $this->addDeposit($user);
        /** @var Campaign $campaign */
        $campaign = Campaign::factory()->make(
            [
                'status' => Campaign::STATUS_ACTIVE,
                'time_start' => (new DateTimeImmutable('-1 month'))->format(DATE_ATOM),
                'user_id' => $user,
            ]
        );
        $banners = [Banner::factory()->make(['campaign_id' => null])];
        /** @var ExchangeRateReader $exchangeRateReader */
        $exchangeRateReader = self::createMock(ExchangeRateReader::class);
        $exchangeRateReader->method('fetchExchangeRate')
            ->willThrowException(new ExchangeRateNotAvailableException());
        $campaignRepository = new CampaignRepository($this->getBannerClassificationCreator(), $exchangeRateReader);

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('Exchange rate is not available');

        $campaignRepository->save($campaign, $banners);

        self::assertDatabaseEmpty(Campaign::class);
        self::assertDatabaseEmpty(Banner::class);
    }

    public function testUpdateAddBanner(): void
    {
        $user = User::factory()->create();
        $this->addDeposit($user);
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

    public function testUpdateAddConversion(): void
    {
        $user = User::factory()->create();
        $this->addDeposit($user);
        /** @var Campaign $campaign */
        $campaign = Campaign::factory()->create(
            [
                'status' => Campaign::STATUS_ACTIVE,
                'time_start' => (new DateTimeImmutable('-1 month'))->format(DATE_ATOM),
                'user_id' => $user,
            ]
        );
        Banner::factory()->create(['campaign_id' => $campaign]);
        $campaignRepository = new CampaignRepository(
            $this->getBannerClassificationCreator(),
            $this->getExchangeRateReader(),
        );
        $conversions = [ConversionDefinition::factory()->make()];

        $campaignRepository->update($campaign, conversionsToInsert: $conversions);

        self::assertCount(1, $campaign->conversions);
    }

    public function testUpdateEditConversion(): void
    {
        $user = User::factory()->create();
        $this->addDeposit($user);
        /** @var Campaign $campaign */
        $campaign = Campaign::factory()->create(
            [
                'status' => Campaign::STATUS_ACTIVE,
                'time_start' => (new DateTimeImmutable('-1 month'))->format(DATE_ATOM),
                'user_id' => $user,
            ]
        );
        Banner::factory()->create(['campaign_id' => $campaign]);
        /** @var ConversionDefinition $conversion */
        $conversion = ConversionDefinition::factory()->create(
            [
                'campaign_id' => $campaign,
                'name' => 'previous-name',
            ]
        );
        $campaignRepository = new CampaignRepository(
            $this->getBannerClassificationCreator(),
            $this->getExchangeRateReader(),
        );
        $conversion->name = 'new-name';
        $conversions = [$conversion];

        $campaignRepository->update($campaign, conversionsToUpdate: $conversions);

        self::assertCount(1, $campaign->conversions);
        self::assertEquals('new-name', $campaign->conversions()->first()->name);
    }

    public function testUpdateDeleteConversion(): void
    {
        $user = User::factory()->create();
        $this->addDeposit($user);
        /** @var Campaign $campaign */
        $campaign = Campaign::factory()->create(
            [
                'status' => Campaign::STATUS_ACTIVE,
                'time_start' => (new DateTimeImmutable('-1 month'))->format(DATE_ATOM),
                'user_id' => $user,
            ]
        );
        Banner::factory()->create(['campaign_id' => $campaign]);
        /** @var ConversionDefinition $conversion */
        $conversion = ConversionDefinition::factory()->create(['campaign_id' => $campaign]);
        $campaignRepository = new CampaignRepository(
            $this->getBannerClassificationCreator(),
            $this->getExchangeRateReader(),
        );
        $conversions = [$conversion->uuid];

        $campaignRepository->update($campaign, conversionUuidsToDelete: $conversions);

        self::assertCount(0, $campaign->conversions);
    }

    public function testUpdateFailWhileCampaignDoesNotExist(): void
    {
        /** @var Campaign $campaign */
        $campaign = Campaign::factory()->make(['status' => Campaign::STATUS_ACTIVE]);
        $campaignRepository = new CampaignRepository(
            $this->getBannerClassificationCreator(),
            $this->getExchangeRateReader(),
        );

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('Function `update` requires existing Campaign model');

        $campaignRepository->update($campaign);

        self::assertDatabaseEmpty(Campaign::class);
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

    private function addDeposit(User $user): void
    {
        UserLedgerEntry::factory()->create(
            [
                'amount' => 50_000 * 1e11,
                'status' => UserLedgerEntry::STATUS_ACCEPTED,
                'type' => UserLedgerEntry::TYPE_DEPOSIT,
                'user_id' => $user,
            ]
        );
    }
}
