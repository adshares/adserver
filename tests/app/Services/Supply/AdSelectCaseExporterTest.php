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

namespace Adshares\Adserver\Tests\Services\Supply;

use Adshares\Adserver\Models\AdsPayment;
use Adshares\Adserver\Models\NetworkBoostPayment;
use Adshares\Adserver\Models\NetworkCampaign;
use Adshares\Adserver\Services\Supply\AdSelectCaseExporter;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Supply\Application\Service\AdSelect;
use Symfony\Component\Console\Output\ConsoleOutput;

class AdSelectCaseExporterTest extends TestCase
{
    public function testExportBoostPayments(): void
    {
        $networkBoostPayment = NetworkBoostPayment::factory()->create([
            'ads_payment_id' => AdsPayment::factory()->create(),
            'network_campaign_id' => NetworkCampaign::factory()->create(),
        ]);
        $adSelectMock = self::createMock(AdSelect::class);
        $adSelectMock->expects(self::once())
            ->method('getLastExportedBoostPaymentId')
            ->willReturn($networkBoostPayment->id - 1);
        $adSelectMock->expects(self::once())
            ->method('exportBoostPayments');
        $adSelectCaseExporter = new AdSelectCaseExporter($adSelectMock, new ConsoleOutput());
        $expectedCount = 1;

        $count = $adSelectCaseExporter->exportBoostPayments();

        self::assertEquals($expectedCount, $count);
    }

    public function testExportBoostPaymentsWhileNoNewPayments(): void
    {
        $adSelectMock = self::createMock(AdSelect::class);
        $adSelectMock->expects(self::once())
            ->method('getLastExportedBoostPaymentId')
            ->willReturn(1);
        $adSelectCaseExporter = new AdSelectCaseExporter($adSelectMock, new ConsoleOutput());
        $expectedCount = 0;

        $count = $adSelectCaseExporter->exportBoostPayments();

        self::assertEquals($expectedCount, $count);
    }

    public function testGetBoostPaymentIdToExport(): void
    {
        $adSelectMock = self::createMock(AdSelect::class);
        $adSelectMock->expects(self::once())
            ->method('getLastExportedBoostPaymentId')
            ->willReturn(53);
        $adSelectCaseExporter = new AdSelectCaseExporter($adSelectMock, new ConsoleOutput());
        $expectedId = 54;

        $id = $adSelectCaseExporter->getBoostPaymentIdToExport();

        self::assertEquals($expectedId, $id);
    }

    public function testGetBoostPaymentIdToExportWhileNothingExported(): void
    {
        $adSelectMock = self::createMock(AdSelect::class);
        $adSelectMock->expects(self::once())
            ->method('getLastExportedBoostPaymentId')
            ->willReturn(0);
        $adSelectCaseExporter = new AdSelectCaseExporter($adSelectMock, new ConsoleOutput());
        $expectedId = NetworkBoostPayment::factory()->create([
            'ads_payment_id' => AdsPayment::factory()->create(),
            'network_campaign_id' => NetworkCampaign::factory()->create(),
        ])->id;

        $id = $adSelectCaseExporter->getBoostPaymentIdToExport();

        self::assertEquals($expectedId, $id);
    }

    public function testGetBoostPaymentIdToExportWhileNothingExportedAndNoPayments(): void
    {
        $adSelectMock = self::createMock(AdSelect::class);
        $adSelectMock->expects(self::once())
            ->method('getLastExportedBoostPaymentId')
            ->willReturn(0);
        $adSelectCaseExporter = new AdSelectCaseExporter($adSelectMock, new ConsoleOutput());

        self::expectException(RuntimeException::class);

        $adSelectCaseExporter->getBoostPaymentIdToExport();
    }
}
