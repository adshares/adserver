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

declare(strict_types = 1);

namespace Adshares\Adserver\Tests\Console\Commands;

use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Tests\Console\TestCase;
use Adshares\Demand\Application\Service\AdPay;
use DateTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\MockObject\Stub;
use function factory;

class AdPayCampaignExportCommandTest extends TestCase
{
    use RefreshDatabase;

    public function testHandle(): void
    {
        $user = factory(User::class)->create();
        factory(Campaign::class)->create(['user_id' => $user->id, 'status' => Campaign::STATUS_ACTIVE]);
        factory(Campaign::class)->create(['user_id' => $user->id, 'status' => Campaign::STATUS_INACTIVE]);
        factory(Campaign::class)->create(
            ['user_id' => $user->id, 'status' => Campaign::STATUS_INACTIVE, 'deleted_at' => new DateTime()]
        );

        $this->app->bind(
            AdPay::class,
            function () {
                $adPayService = $this->createMock(AdPay::class);
                $adPayService->expects(self::once())->method('updateCampaign')->will(
                    self::assertCampaignCount(1)
                );
                $adPayService->expects(self::once())->method('deleteCampaign')->will(
                    self::assertCampaignCount(2)
                );

                return $adPayService;
            }
        );

        $this->artisan('ops:adpay:campaign:export')
            ->assertExitCode(0);
    }

    private static function assertCampaignCount(int $expectedCount): Stub
    {
        return self::returnCallback(
            function (array $campaigns) use ($expectedCount) {
                self::assertCount($expectedCount, $campaigns);
            }
        );
    }

    public function testHandleEmptyDb(): void
    {
        $this->app->bind(
            AdPay::class,
            function () {
                $adPayService = $this->createMock(AdPay::class);
                $adPayService->expects(self::never())->method('updateCampaign');
                $adPayService->expects(self::never())->method('deleteCampaign');

                return $adPayService;
            }
        );

        $this->artisan('ops:adpay:campaign:export')
            ->assertExitCode(0);
    }
}
