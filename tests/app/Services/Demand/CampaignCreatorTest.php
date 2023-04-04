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

namespace Adshares\Adserver\Tests\Services\Demand;

use Adshares\Adserver\Models\BidStrategy;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Services\Demand\CampaignCreator;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Application\Service\ConfigurationRepository;
use Adshares\Common\Exception\InvalidArgumentException;
use Adshares\Common\Exception\RuntimeException;
use DateTimeImmutable;
use DateTimeInterface;

final class CampaignCreatorTest extends TestCase
{
    public function testPrepareCampaignFromInput(): void
    {
        $creator = new CampaignCreator($this->app->make(ConfigurationRepository::class));

        $campaign = $creator->prepareCampaignFromInput(self::getCampaignData());

        self::assertInstanceOf(Campaign::class, $campaign);
    }

    /**
     * @dataProvider prepareCampaignFromInputInvalidProvider
     */
    public function testPrepareCampaignFromInputInvalid(array $input): void
    {
        $creator = new CampaignCreator($this->app->make(ConfigurationRepository::class));

        self::expectException(InvalidArgumentException::class);

        $creator->prepareCampaignFromInput($input);
    }

    public function prepareCampaignFromInputInvalidProvider(): array
    {
        return [
            'missing budget' => [self::getCampaignData([], 'budget')],
            'missing date_start' => [self::getCampaignData([], 'date_start')],
            'missing medium' => [self::getCampaignData([], 'medium')],
            'missing name' => [self::getCampaignData([], 'name')],
            'missing status' => [self::getCampaignData([], 'status')],
            'missing target_url' => [self::getCampaignData([], 'target_url')],
            'invalid date_end earlier than date_start' => [self::getCampaignData([
                'date_start' => (new DateTimeImmutable('+2 days'))->format(DateTimeInterface::ATOM),
                'date_end' => (new DateTimeImmutable('+1 days'))->format(DateTimeInterface::ATOM),
            ])],
            'invalid date_end outdated' => [self::getCampaignData([
                'date_start' => (new DateTimeImmutable('-2 days'))->format(DateTimeInterface::ATOM),
                'date_end' => (new DateTimeImmutable('-1 days'))->format(DateTimeInterface::ATOM),
            ])],
            'invalid date_start type' => [self::getCampaignData(['date_start' => 0])],
            'invalid date_start format' => [self::getCampaignData(['date_start' => 'now'])],
            'invalid max_cpm negative' => [self::getCampaignData(['max_cpm' => -1])],
            'invalid max_cpm too low' => [self::getCampaignData(['max_cpm' => 0.001])],
            'invalid max_cpm type' => [self::getCampaignData(['max_cpm' => 'auto'])],
            'invalid medium' => [self::getCampaignData(['medium' => 'invalid'])],
            'invalid name type' => [self::getCampaignData(['name' => 1])],
            'invalid name empty' => [self::getCampaignData(['name' => ''])],
            'invalid name too long' => [self::getCampaignData([
                'name' => str_repeat('a', Campaign::NAME_MAXIMAL_LENGTH + 1),
                ])],
            'invalid status type' => [self::getCampaignData(['status' => 'invalid'])],
            'invalid status unknown' => [self::getCampaignData(['status' => 1024])],
            'invalid target_url' => [self::getCampaignData(['target_url' => 'invalid'])],
        ];
    }

    public function testPrepareCampaignFromInputNoBidStrategy(): void
    {
        BidStrategy::all()->each(fn($bidStrategy) => $bidStrategy->delete());
        $creator = new CampaignCreator($this->app->make(ConfigurationRepository::class));
        self::expectException(RuntimeException::class);

        $creator->prepareCampaignFromInput(self::getCampaignData());
    }

    private static function getCampaignData(array $merge = [], string $remove = null): array
    {
        $data = array_merge([
            'status' => 'active',
            'name' => 'Test campaign',
            'target_url' => 'https://exmaple.com/landing',
            'max_cpc' => 0,
            'max_cpm' => null,
            'budget' => 10,
            'medium' => 'web',
            'vendor' => null,
            'date_start' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
            'date_end' => (new DateTimeImmutable('+2 days'))->format(DateTimeInterface::ATOM),
            'targeting' => [
                'requires' => [
                    'site' => [
                        'category' => ['news', 'technology'],
                        'quality' => ['high'],
                    ],
                ],
                'excludes' => [
                    'device' => [
                        'browser' => ['other'],
                    ],
                ],
            ],
        ], $merge);

        if ($remove) {
            unset($data[$remove]);
        }
        return $data;
    }

    public function testUpdateCampaignStatus(): void
    {
        $campaign = Campaign::factory()->create(['status' => Campaign::STATUS_ACTIVE]);
        $creator = new CampaignCreator($this->app->make(ConfigurationRepository::class));

        $updatedCampaign = $creator->updateCampaign(['status' => 'inactive'], $campaign);

        self::assertEquals(Campaign::STATUS_INACTIVE, $updatedCampaign->status);
    }

    /**
     * @dataProvider updateCampaignInvalidProvider
     */
    public function testUpdateCampaignInvalid(array $data): void
    {
        $campaign = Campaign::factory()->create();
        $creator = new CampaignCreator($this->app->make(ConfigurationRepository::class));

        self::expectException(InvalidArgumentException::class);

        $creator->updateCampaign($data, $campaign);
    }

    public function updateCampaignInvalidProvider(): array
    {
        return [
            'invalid bid_strategy_uuid type' => [['bid_strategy_uuid' => 0]],
            'invalid date range' => [[
                'date_start' => (new DateTimeImmutable('+2 day'))->format(DateTimeInterface::ATOM),
                'date_end' => (new DateTimeImmutable('+1 day'))->format(DateTimeInterface::ATOM),
            ]],
        ];
    }
}
