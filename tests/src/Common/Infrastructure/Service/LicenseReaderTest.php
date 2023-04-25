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

namespace Adshares\Tests\Common\Infrastructure\Service;

use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Application\Service\LicenseVault;
use Adshares\Common\Domain\ValueObject\AccountId;
use Adshares\Common\Domain\ValueObject\Commission;
use Adshares\Common\Domain\ValueObject\License;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Common\Infrastructure\Service\LicenseReader;
use DateTimeImmutable;

class LicenseReaderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        apcu_clear_cache();
    }

    protected function tearDown(): void
    {
        apcu_clear_cache();
        parent::tearDown();
    }

    public function testGetAddress(): void
    {
        $licenseVault = self::createMock(LicenseVault::class);
        $licenseVault->expects(self::once())
            ->method('read')
            ->willReturn($this->getLicense());
        $reader = new LicenseReader($licenseVault);

        $address = $reader->getAddress();

        self::assertInstanceOf(AccountId::class, $address);
        self::assertEquals('0001-00000024-FF89', $address->toString());
    }

    public function testGetAddressFromCache(): void
    {
        apcu_store('licence-account', '0001-00000024-FF89');
        $licenseVault = self::createMock(LicenseVault::class);
        $licenseVault->expects(self::never())->method('read');
        $reader = new LicenseReader($licenseVault);

        $address = $reader->getAddress();

        self::assertInstanceOf(AccountId::class, $address);
        self::assertEquals('0001-00000024-FF89', $address->toString());
    }

    public function testGetInfoBox(): void
    {
        $licenseVault = self::createMock(LicenseVault::class);
        $licenseVault->expects(self::once())
            ->method('read')
            ->willReturn($this->getLicense());
        $reader = new LicenseReader($licenseVault);

        $infoBox = $reader->getInfoBox();

        self::assertFalse($infoBox);
    }

    public function testGetInfoBoxWhileError(): void
    {
        $licenseVault = self::createMock(LicenseVault::class);
        $licenseVault->expects(self::once())
            ->method('read')
            ->willThrowException(new RuntimeException('test-exception'));
        $reader = new LicenseReader($licenseVault);

        $infoBox = $reader->getInfoBox();

        self::assertTrue($infoBox);
    }

    private function getLicense(): License
    {
        return new License(
            'COM-aBcD02',
            'COM',
            1,
            new DateTimeImmutable('@1658764323'),
            new DateTimeImmutable('@1690300323'),
            'AdServer',
            new AccountId('0001-00000024-FF89'),
            new Commission(0.0),
            new Commission(0.01),
            new Commission(0.02),
            false
        );
    }
}
