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

use Adshares\Adserver\Exceptions\MissingInitialConfigurationException;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Application\Service\AdClassify;
use Adshares\Common\Application\Service\ConfigurationRepository;
use Adshares\Mock\Client\DummyAdClassifyClient;
use Adshares\Mock\Repository\DummyConfigurationRepository;
use Symfony\Component\HttpFoundation\Response;

final class OptionsControllerTest extends TestCase
{
    private const ZONES_URI = '/api/options/sites/zones';

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
        $response->assertStatus(Response::HTTP_OK)
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
        $response->assertStatus(Response::HTTP_OK)
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

        $response->assertStatus(Response::HTTP_OK)
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

        $response->assertStatus(Response::HTTP_OK)
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
        $response->assertStatus(Response::HTTP_OK)
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

    public function testFilteringWhileMissingTaxonomy(): void
    {
        $this->mockRepositoryWhileMissingTaxonomy();
        $this->login();

        $response = self::getJson('/api/options/sites/filtering');
        $response->assertStatus(Response::HTTP_OK)
            ->assertExactJson([]);
    }

    public function testMedia(): void
    {
        $this->login();

        $response = self::get('/api/options/campaigns/media');
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson(['web' => 'Website', 'metaverse' => 'Metaverse']);
    }

    public function testMediaWhileMissingTaxonomy(): void
    {
        $this->mockRepositoryWhileMissingTaxonomy();
        $this->login();

        $response = self::get('/api/options/campaigns/media');
        $response->assertStatus(Response::HTTP_OK);
        $response->assertExactJson([]);
    }

    public function testMedium(): void
    {
        $this->login();

        $response = self::get('/api/options/campaigns/media/web');
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['name' => 'web', 'label' => 'Website']);
        $response->assertJsonFragment(['apple-os' => 'Apple OS']);
    }

    public function testMediumWhileMissingTaxonomy(): void
    {
        $this->mockRepositoryWhileMissingTaxonomy();
        $this->login();

        $response = self::get('/api/options/campaigns/media/web');
        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testMediumExcludeQuality(): void
    {
        $this->login();

        $response = self::get('/api/options/campaigns/media/web?e=1');
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonMissing(['label' => 'Quality', 'name' => 'quality']);
    }

    public function testMetaverseVendors(): void
    {
        $this->login();

        $response = self::get('/api/options/campaigns/media/metaverse/vendors');
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['decentraland' => 'Decentraland']);
    }

    public function testUserRoles(): void
    {
        $this->login();

        $response = self::get('/api/options/server/default-user-roles');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertExactJson(['defaultUserRoles' => ['advertiser', 'publisher']]);
    }

    public function testWebVendors(): void
    {
        $this->login();

        $response = self::get('/api/options/campaigns/media/web/vendors');
        $response->assertStatus(Response::HTTP_OK);
        $response->assertExactJson([]);
    }

    public function testWebVendorsWhileMissingTaxonomy(): void
    {
        $this->mockRepositoryWhileMissingTaxonomy();
        $this->login();

        $response = self::get('/api/options/campaigns/media/web/vendors');
        $response->assertStatus(Response::HTTP_OK);
        $response->assertExactJson([]);
    }

    public function testZones(): void
    {
        $this->login();

        $response = self::get(self::ZONES_URI);

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(['*' => ['label', 'size', 'type']]);
    }

    public function testZonesWhileMissingTaxonomy(): void
    {
        $this->mockRepositoryWhileMissingTaxonomy();
        $this->login();

        $response = self::get(self::ZONES_URI);

        $response->assertStatus(Response::HTTP_OK)
            ->assertExactJson([]);
    }

    private function mockRepositoryWhileMissingTaxonomy(): void
    {
        $mock = self::createMock(ConfigurationRepository::class);
        foreach (['fetchFilteringOptions', 'fetchMedia', 'fetchMedium', 'fetchTaxonomy'] as $functionName) {
            $mock->method($functionName)->willThrowException(new MissingInitialConfigurationException('test'));
        }
        $this->app->bind(ConfigurationRepository::class, fn() => $mock);
    }
}
