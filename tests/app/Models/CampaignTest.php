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

namespace Adshares\Adserver\Tests\Models;

use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\ConversionDefinition;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Application\Dto\ExchangeRate;
use Closure;
use DateTime;
use DateTimeInterface;

class CampaignTest extends TestCase
{
    private const INVALID_CAMPAIGN_STATUS = -1;

    /**
     * @dataProvider campaignTimeEndProvider
     *
     * @param bool $expectedResult
     * @param string|null $timeEnd
     */
    public function testCampaignOutdated(bool $expectedResult, ?string $timeEnd): void
    {
        $campaign = new Campaign();
        $campaign->time_end = $timeEnd;

        self::assertEquals($expectedResult, $campaign->isOutdated());
    }

    public function campaignTimeEndProvider(): array
    {
        return [
            [false, null],
            [false, (new DateTime('+1 hour'))->format(DateTimeInterface::ATOM)],
            [true, (new DateTime())->format(DateTimeInterface::ATOM)],
            [true, (new DateTime('-1 hour'))->format(DateTimeInterface::ATOM)],
        ];
    }

    /**
     * @dataProvider campaignChangeStatusProvider
     *
     * @param bool $expectedResult
     * @param Closure $campaign
     * @param int $status
     * @return void
     */
    public function testCampaignChangeStatus(bool $expectedResult, Closure $campaign, int $status): void
    {
        $exchangeRate = new ExchangeRate(new DateTime(), 1.0, 'USD');

        self::assertEquals($expectedResult, $campaign()->changeStatus($status, $exchangeRate));
    }

    public function campaignChangeStatusProvider(): array
    {
        return [
            'invalid status' => [
                false,
                function () {
                    return factory(Campaign::class)->create(
                        [
                            'user_id' => factory(User::class)->create()->id,
                            'status' => Campaign::STATUS_INACTIVE,
                        ]
                    );
                },
                self::INVALID_CAMPAIGN_STATUS,
            ],
            'same status' => [
                false,
                function () {
                    return factory(Campaign::class)->create(
                        [
                            'user_id' => factory(User::class)->create()->id,
                            'status' => Campaign::STATUS_INACTIVE,
                        ]
                    );
                },
                Campaign::STATUS_INACTIVE,
            ],
            'low budget' => [
                false,
                function () {
                    return factory(Campaign::class)->create(
                        [
                            'user_id' => factory(User::class)->create()->id,
                            'status' => Campaign::STATUS_INACTIVE,
                            'budget' => 0,
                        ]
                    );
                },
                Campaign::STATUS_ACTIVE,
            ],
            'outdated campaign' => [
                false,
                function () {
                    return factory(Campaign::class)->create(
                        [
                            'user_id' => factory(User::class)->create()->id,
                            'status' => Campaign::STATUS_INACTIVE,
                            'time_end' => (new DateTime('-1 month'))->format(DATE_ATOM),
                        ]
                    );
                },
                Campaign::STATUS_ACTIVE,
            ],
            'insufficient funds on account' => [
                false,
                function () {
                    return factory(Campaign::class)->create(
                        [
                            'user_id' => factory(User::class)->create()->id,
                            'status' => Campaign::STATUS_INACTIVE,
                        ]
                    );
                },
                Campaign::STATUS_ACTIVE,
            ],
            'auto cpm' => [
                true,
                function () {
                    $userId = factory(User::class)->create()->id;
                    factory(UserLedgerEntry::class)->create(
                        [
                            'user_id' => $userId,
                            'amount' => 1000 * 1e11,
                        ]
                    );

                    return factory(Campaign::class)->create(
                        [
                            'user_id' => $userId,
                            'status' => Campaign::STATUS_INACTIVE,
                            'max_cpm' => null,
                        ]
                    );
                },
                Campaign::STATUS_ACTIVE,
            ],
            'low cpm' => [
                false,
                function () {
                    $userId = factory(User::class)->create()->id;
                    factory(UserLedgerEntry::class)->create(
                        [
                            'user_id' => $userId,
                            'amount' => 1000 * 1e11,
                        ]
                    );

                    return factory(Campaign::class)->create(
                        [
                            'user_id' => $userId,
                            'status' => Campaign::STATUS_INACTIVE,
                            'max_cpm' => 1,
                        ]
                    );
                },
                Campaign::STATUS_ACTIVE,
            ],
            'low cpm and conversion' => [
                true,
                function () {
                    $userId = factory(User::class)->create()->id;
                    factory(UserLedgerEntry::class)->create(
                        [
                            'user_id' => $userId,
                            'amount' => 1000 * 1e11,
                        ]
                    );
                    $campaign = factory(Campaign::class)->create(
                        [
                            'user_id' => $userId,
                            'status' => Campaign::STATUS_INACTIVE,
                            'max_cpm' => 1,
                        ]
                    );
                    factory(ConversionDefinition::class)->create(
                        [
                            'campaign_id' => $campaign->id,
                            'value' => 10 ** 10,
                        ]
                    );

                    return $campaign;
                },
                Campaign::STATUS_ACTIVE,
            ],
        ];
    }
}
