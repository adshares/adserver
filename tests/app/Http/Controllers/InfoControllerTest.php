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

namespace Adshares\Adserver\Tests\Http\Controllers;

use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\PanelPlaceholder;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Config\RegistrationMode;
use Illuminate\Http\Response;

class InfoControllerTest extends TestCase
{
    private const URI_INFO = '/info';
    private const URI_PANEL_PLACEHOLDERS_LOGIN = '/panel/placeholders/login';

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
                'serverUrl' => 'https://example.com',
                'panelUrl' => 'http://adpanel',
                'privacyUrl' => 'https://example.com/policies/privacy.html',
                'termsUrl' => 'https://example.com/policies/terms.html',
                'inventoryUrl' => 'https://example.com/adshares/inventory/list',
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
                'mode' => 'operational',
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

    public function testGetPanelPlaceholdersLoginWhenNotSet(): void
    {
        $response = $this->getJson(self::URI_PANEL_PLACEHOLDERS_LOGIN);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertExactJson([
            'loginInfo' => null,
            'advertiserApplyFormUrl' => null,
            'publisherApplyFormUrl' => null,
        ]);
    }

    public function testGetPanelPlaceholdersLogin(): void
    {
        PanelPlaceholder::register(PanelPlaceholder::construct(PanelPlaceholder::TYPE_LOGIN_INFO, '<div>Hello</div>'));
        Config::updateAdminSettings([
            Config::ADVERTISER_APPLY_FORM_URL => 'https://example.com/advertisers',
            Config::PUBLISHER_APPLY_FORM_URL => 'https://example.com/publishers',
        ]);

        $response = $this->getJson(self::URI_PANEL_PLACEHOLDERS_LOGIN);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertExactJson([
            'loginInfo' => '<div>Hello</div>',
            'advertiserApplyFormUrl' => 'https://example.com/advertisers',
            'publisherApplyFormUrl' => 'https://example.com/publishers',
        ]);
    }
}
