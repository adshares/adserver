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

namespace Adshares\Adserver\Tests\Http\Controllers\Manager;

use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Application\Service\AdClassify;
use Adshares\Common\Application\Service\ConfigurationRepository;
use Adshares\Mock\Client\DummyAdClassifyClient;
use Adshares\Mock\Repository\DummyConfigurationRepository;

final class OptionsControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(
            AdClassify::class,
            static function () {
                return new DummyAdClassifyClient();
            }
        );
        $this->app->bind(
            ConfigurationRepository::class,
            static function () {
                return new DummyConfigurationRepository();
            }
        );
    }

    public function testBanners(): void
    {
        $expectedFields = [
            'uploadLimitImage',
            'uploadLimitModel',
            'uploadLimitVideo',
            'uploadLimitZip',
        ];
        $this->login();

        $response = self::getJson('/api/options/banners');
        $response->assertStatus(200)
            ->assertJsonStructure($expectedFields);

        $content = json_decode($response->content(), true);
        foreach ($expectedFields as $expectedField) {
            self::assertIsInt($content[$expectedField]);
        }
    }

    public function testCampaigns(): void
    {
        $expectedFields = [
            'minBudget',
            'minCpm',
            'minCpa',
        ];
        $this->login();

        $response = self::getJson('/api/options/campaigns');
        $response->assertStatus(200)
            ->assertJsonStructure($expectedFields);

        $content = json_decode($response->content(), true);
        foreach ($expectedFields as $expectedField) {
            self::assertIsInt($content[$expectedField]);
        }
    }

    public function testServer(): void
    {
        $expectedStructure = [
            'appCurrency' => 'ADS',
            'displayCurrency' => 'USD',
            'supportChat' => null,
            'supportEmail' => 'mail@example.com',
            'supportTelegram' => null,
        ];
        $this->login();

        $response = $this->get('/api/options/server');

        $response->assertStatus(200)
            ->assertJsonStructure(array_keys($expectedStructure));
        foreach ($expectedStructure as $key => $expectedValue) {
            $response->assertJsonPath($key, $expectedValue);
        }
    }

    public function testSites(): void
    {
        $expectedFields = [
            'classifierLocalBanners',
            'acceptBannersManually',
        ];
        $this->login();

        $response = $this->get('/api/options/sites');

        $response->assertStatus(200)
            ->assertJsonStructure($expectedFields);
        $content = json_decode($response->content(), true);
        self::assertEquals(0, $content['acceptBannersManually']);
        self::assertEquals('all-by-default', $content['classifierLocalBanners']);
    }

    private static function assertStructure(array $content): void
    {
        foreach ($content as $item) {
            if ($item['children'] ?? false) {
                self::assertNotEmpty($item['children']);
                self::assertFalse($item['values'] ?? false);
                self::assertFalse($item['allowInput'] ?? false);
            } else {
                self::assertIsArray($item['values']);
                self::assertIsBool($item['allowInput']);
            }
            self::assertIsString($item['valueType']);
            self::assertIsString($item['key']);
            self::assertIsString($item['label']);
        }
    }

    public function testFiltering(): void
    {
        $this->login();

        $response = self::getJson('/api/options/sites/filtering');
        $response->assertStatus(200)
            ->assertJsonStructure(
                [
                    '*' => [
                        'key',
                        'label',
                    ],
                ]
            );

        $content = json_decode($response->content(), true);
        self::assertStructure($content);
    }

    public function testMedia(): void
    {
        $this->login();

        $response = self::get('/api/options/campaigns/media');
        $response->assertStatus(200);
        $response->assertJson(['web' => 'Website', 'metaverse' => 'Metaverse']);
    }

    public function testMedium(): void
    {
        $this->login();

        $response = self::get('/api/options/campaigns/media/web');
        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'web', 'label' => 'Website']);
        $response->assertJsonFragment(['apple-os' => 'Apple OS']);
    }

    public function testMediumExcludeQuality(): void
    {
        $this->login();

        $response = self::get('/api/options/campaigns/media/web?e=1');
        $response->assertStatus(200);
        $response->assertJsonMissing(['label' => 'Quality', 'name' => 'quality']);
    }

    public function testMetaverseVendors(): void
    {
        $this->login();

        $response = self::get('/api/options/campaigns/media/metaverse/vendors');
        $response->assertStatus(200);
        $response->assertJsonFragment(['decentraland' => 'Decentraland']);
    }

    public function testUserRoles(): void
    {
        $this->login();

        $response = self::get('/api/options/server/default-user-roles');

        $response->assertStatus(200);
        $response->assertExactJson(['defaultUserRoles' => ['advertiser', 'publisher']]);
    }

    public function testWebVendors(): void
    {
        $this->login();

        $response = self::get('/api/options/campaigns/media/web/vendors');
        $response->assertStatus(200);
        $response->assertExactJson([]);
    }
}
