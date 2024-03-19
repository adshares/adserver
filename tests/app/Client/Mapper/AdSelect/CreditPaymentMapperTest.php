<?php

/**
 * Copyright (c) 2018-2024 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Tests\Client\Mapper\AdSelect;

use Adshares\Adserver\Client\Mapper\AdSelect\BoostPaymentMapper;
use Adshares\Adserver\Models\AdsPayment;
use Adshares\Adserver\Models\NetworkCampaign;
use Adshares\Adserver\Models\NetworkBoostPayment;
use Adshares\Adserver\Tests\TestCase;
use DateTimeImmutable;
use DateTimeInterface;

final class CreditPaymentMapperTest extends TestCase
{
    private const EXPECTED_KEYS = [
        'id',
        'campaign_id',
        'paid_amount',
        'pay_time',
        'payer',
    ];

    public function testMap(): void
    {
        $adsPayment = AdsPayment::factory()->create(['address' => '0001-00000001-8B4E']);
        $campaign = NetworkCampaign::factory()->create(['uuid' => '312064267be539d5a73d70ade6d08139']);
        $expectedBoostPaymentId = NetworkBoostPayment::factory()->create([
            'pay_time' => DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, '2024-02-23T12:51:34+00:00'),
            'ads_payment_id' => $adsPayment,
            'network_campaign_id' => $campaign,
            'total_amount' => 120_000_000_000,
            'exchange_rate' => 3.2,
        ])->id;
        $boostPayment = NetworkBoostPayment::fetchPaymentsToExport(0, 1)->first();

        $mapped = BoostPaymentMapper::map($boostPayment);

        foreach (self::EXPECTED_KEYS as $key) {
            self::assertArrayHasKey($key, $mapped);
        }
        self::assertEquals($expectedBoostPaymentId, $mapped['id']);
        self::assertEquals('2024-02-23T12:51:34+00:00', $mapped['pay_time']);
        self::assertEquals(384_000_000_000, $mapped['paid_amount']);
        self::assertEquals('0001-00000001-8B4E', $mapped['payer']);
        self::assertEquals('312064267be539d5a73d70ade6d08139', $mapped['campaign_id']);
    }
}
