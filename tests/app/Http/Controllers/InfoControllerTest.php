<?php

/**
 * Copyright (c) 2018-2021 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Tests\Http\Controllers;

use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Config\RegistrationMode;
use Illuminate\Http\Response;

class InfoControllerTest extends TestCase
{
    private const URI_INFO = '/info';

    public function testDefaultInfo(): void
    {
        $response = $this->getJson(self::URI_INFO);
        $response->assertStatus(Response::HTTP_OK);
        $this->assertEquals(
            [
                'module' => 'adserver',
                'name' => 'AdServer',
                'version' => '#',
                'capabilities' => [
                    'ADV',
                    'PUB'
                ],
                'serverUrl' => 'https://test',
                'panelUrl' => 'http://adpanel',
                'privacyUrl' => 'https://test/policies/privacy.html',
                'termsUrl' => 'https://test/policies/terms.html',
                'inventoryUrl' => 'https://test/adshares/inventory/list',
                'adsAddress' => '0001-00000005-CBCA',
                'supportEmail' => 'mail@example.com',
                'demandFee' => 0.0199,
                'supplyFee' => 0.0199,
                'registrationMode' => 'public',
                'statistics' => [
                    'users' => 0,
                    'campaigns' => 0,
                    'sites' => 0,
                ],
            ],
            $response->json()
        );
    }

    public function testRegistrationModeInfo(): void
    {
        $response = $this->getJson(self::URI_INFO);
        $response->assertStatus(Response::HTTP_OK);
        $this->assertEquals(RegistrationMode::PUBLIC, $response->json('registrationMode'));

        Config::updateAdminSettings([Config::REGISTRATION_MODE => RegistrationMode::PRIVATE]);

        $response = $this->getJson(self::URI_INFO);
        $response->assertStatus(Response::HTTP_OK);
        $this->assertEquals(RegistrationMode::PRIVATE, $response->json('registrationMode'));
    }
}
