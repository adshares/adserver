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

namespace Adshares\Adserver\Tests\Http\Controllers\Manager;

use Adshares\Adserver\Models\User;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Application\Service\LicenseVault;
use Adshares\Common\Domain\ValueObject\AccountId;
use Adshares\Common\Domain\ValueObject\Commission;
use Adshares\Common\Domain\ValueObject\License;
use Adshares\Common\Exception\RuntimeException;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Response;

final class AdminControllerTest extends TestCase
{
    private const URI_LICENSE = '/admin/license';
    private const URI_SETTINGS = '/admin/settings';
    private const SETTINGS_STRUCTURE = [
        'settings' => ['adUserInfoUrl']
    ];

    public function testSettingsStructureUnauthorized(): void
    {
        $this->actingAs(User::factory()->create(), 'api');

        $response = $this->get(self::URI_SETTINGS);
        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function testSettingsStructure(): void
    {
        $this->actingAs(User::factory()->admin()->create(), 'api');

        $response = $this->get(self::URI_SETTINGS);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(self::SETTINGS_STRUCTURE);
    }

    public function testGetLicenseSuccess(): void
    {
        $this->actingAs(User::factory()->admin()->create(), 'api');
        $licenseVault = self::createMock(LicenseVault::class);
        $licenseVault->expects(self::once())->method('read')->willReturn(
            new License(
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
                true
            )
        );
        $this->app->bind(LicenseVault::class, fn() => $licenseVault);

        $response = $this->get(self::URI_LICENSE);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => 'COM-aBcD02']);
    }

    public function testGetLicenseFail(): void
    {
        $this->actingAs(User::factory()->admin()->create(), 'api');
        $licenseVault = self::createMock(LicenseVault::class);
        $licenseVault->expects(self::once())
            ->method('read')
            ->willThrowException(new RuntimeException('test-exception'));
        $this->app->bind(LicenseVault::class, fn() => $licenseVault);

        $response = $this->get(self::URI_LICENSE);

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }
}
