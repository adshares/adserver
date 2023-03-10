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

namespace Adshares\Adserver\Tests\Services\Supply;

use Adshares\Adserver\Http\Utils;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\NetworkBanner;
use Adshares\Adserver\Models\NetworkCampaign;
use Adshares\Adserver\Models\NetworkHost;
use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\Zone;
use Adshares\Adserver\Services\Supply\OpenRtbBridge;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\Utilities\AdsUtils;
use Adshares\Adserver\Utilities\DatabaseConfigReader;
use Adshares\Common\Domain\ValueObject\SecureUrl;
use Adshares\Supply\Application\Dto\FoundBanners;
use Adshares\Supply\Application\Dto\ImpressionContext;
use Adshares\Supply\Application\Service\AdSelect;
use Illuminate\Support\Facades\Http;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

class OpenRtbBridgeTest extends TestCase
{
    public function testIsActiveWhileNotConfigured(): void
    {
        self::assertFalse(OpenRtbBridge::isActive());
    }

    public function testIsActiveWhileConfigured(): void
    {
        $this->initOpenRtbConfiguration();

        self::assertTrue(OpenRtbBridge::isActive());
    }

    public function testReplaceOpenRtbBanners(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'example.com/serve' => Http::response([[
                'request_id' => '0',
                'click_url' => 'https://example.com/click/1',
                'serve_url' => 'https://example.com/serve/1',
                'view_url' => 'https://example.com/view/1',
            ]]),
        ]);
        $initiallyFoundBanners = $this->getFoundBanners();
        $context = new ImpressionContext([], [], []);

        $foundBanners = (new OpenRtbBridge())->replaceOpenRtbBanners($initiallyFoundBanners, $context);

        self::assertCount(1, $foundBanners);
        self::assertEquals('3', $foundBanners->first()['request_id']);
        Http::assertSentCount(1);
    }

    public function testReplaceOpenRtbBannersWhileEmptyResponse(): void
    {
        Http::preventStrayRequests();
        Http::fake(['example.com/serve' => Http::response([])]);
        $initiallyFoundBanners = $this->getFoundBanners();
        $context = new ImpressionContext([], [], []);

        $foundBanners = (new OpenRtbBridge())->replaceOpenRtbBanners($initiallyFoundBanners, $context);

        self::assertCount(1, $foundBanners);
        self::assertNull($foundBanners->first());
        Http::assertSentCount(1);
    }

    public function testReplaceOpenRtbBannersWhileNoBannersFromBridge(): void
    {
        Http::preventStrayRequests();
        Http::fake();
        $initiallyFoundBanners = $this->getFoundBanners();
        $banner = $initiallyFoundBanners->get(0);
        $banner['pay_from'] = '0001-00000004-DBEB';
        $initiallyFoundBanners->set(0, $banner);
        $context = new ImpressionContext([], [], []);

        $foundBanners = (new OpenRtbBridge())->replaceOpenRtbBanners($initiallyFoundBanners, $context);

        self::assertCount(1, $foundBanners);
        self::assertEquals('3', $foundBanners->first()['request_id']);
        Http::assertNothingSent();
    }

    public function testReplaceOpenRtbBannersWhileInvalidStatus(): void
    {
        Http::preventStrayRequests();
        Http::fake(['example.com/serve' => Http::response(status: Response::HTTP_NOT_FOUND)]);
        $initiallyFoundBanners = $this->getFoundBanners();
        $context = new ImpressionContext([], [], []);

        $foundBanners = (new OpenRtbBridge())->replaceOpenRtbBanners($initiallyFoundBanners, $context);

        self::assertCount(1, $foundBanners);
        self::assertNull($foundBanners->first());
        Http::assertSentCount(1);
    }

    /**
     * @dataProvider replaceOpenRtbBannersWhileInvalidResponseProvider
     */
    public function testReplaceOpenRtbBannersWhileInvalidResponse(mixed $response): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'example.com/serve' => Http::response($response),
        ]);
        $initiallyFoundBanners = $this->getFoundBanners();
        $context = new ImpressionContext([], [], []);

        $foundBanners = (new OpenRtbBridge())->replaceOpenRtbBanners($initiallyFoundBanners, $context);

        self::assertCount(1, $foundBanners);
        self::assertNull($foundBanners->first());
        Http::assertSentCount(1);
    }

    public function replaceOpenRtbBannersWhileInvalidResponseProvider(): array
    {
        return [
            'not existing request id' => [[[
                'request_id' => '1',
                'click_url' => 'https://example.com/click/1',
                'serve_url' => 'https://example.com/serve/1',
                'view_url' => 'https://example.com/view/1',
            ]]],
            'no request id' => [[[
                'click_url' => 'https://example.com/click/1',
                'serve_url' => 'https://example.com/serve/1',
                'view_url' => 'https://example.com/view/1',
            ]]],
            'no click url' => [[[
                'request_id' => '0',
                'serve_url' => 'https://example.com/serve/1',
                'view_url' => 'https://example.com/view/1',
            ]]],
            'no serve url' => [[[
                'request_id' => '0',
                'click_url' => 'https://example.com/click/1',
                'view_url' => 'https://example.com/view/1',
            ]]],
            'no view url' => [[[
                'request_id' => '0',
                'click_url' => 'https://example.com/click/1',
                'serve_url' => 'https://example.com/serve/1',
            ]]],
            'invalid serve url type' => [[[
                'request_id' => '0',
                'click_url' => 'https://example.com/click/1',
                'serve_url' => 1234,
                'view_url' => 'https://example.com/view/1',
            ]]],
            'entry is not array' => [['0']],
            'content is not array' => ['0'],
        ];
    }

    private function initOpenRtbConfiguration(array $settings = []): void
    {
        $mergedSettings = array_merge(
            [
                Config::OPEN_RTB_BRIDGE_ACCOUNT_ADDRESS => '0001-00000001-8B4E',
                Config::OPEN_RTB_BRIDGE_URL => 'https://example.com',
            ],
            $settings,
        );
        Config::updateAdminSettings($mergedSettings);
        DatabaseConfigReader::overwriteAdministrationConfig();
    }

    private function getFoundBanners(): FoundBanners
    {
        $this->initOpenRtbConfiguration();
        NetworkHost::factory()->create([
            'address' => '0001-00000001-8B4E',
            'host' => 'https://example.com',
        ]);
        /** @var Zone $zone */
        $zone = Zone::factory()->create([
            'site_id' => Site::factory()->create([
                'user_id' => User::factory()->create(['api_token' => '1234', 'auto_withdrawal' => 1e11]),
                'status' => Site::STATUS_ACTIVE,
            ]),
        ]);
        /** @var NetworkBanner $networkBanner */
        $networkBanner = NetworkBanner::factory()->create([
            'network_campaign_id' => NetworkCampaign::factory()->create(),
            'serve_url' => 'https://example.com/serve/' . Uuid::uuid4()->toString(),
        ]);
        $impressionId = Uuid::uuid4();

        return new FoundBanners([
            [
                'id' => $networkBanner->uuid,
                'demandId' => $networkBanner->demand_banner_id,
                'publisher_id' => '0123456879ABCDEF0123456879ABCDEF',
                'zone_id' => $zone->uuid,
                'pay_from' => '0001-00000001-8B4E',
                'pay_to' => AdsUtils::normalizeAddress(config('app.adshares_address')),
                'type' => $networkBanner->type,
                'size' => $networkBanner->size,
                'serve_url' => $networkBanner->serve_url,
                'creative_sha1' => '',
                'click_url' => SecureUrl::change(
                    route(
                        'log-network-click',
                        [
                            'id' => $networkBanner->uuid,
                            'iid' => $impressionId,
                            'r' => Utils::urlSafeBase64Encode($networkBanner->click_url),
                            'zid' => $zone->uuid,
                        ]
                    )
                ),
                'view_url' => SecureUrl::change(
                    route(
                        'log-network-view',
                        [
                            'id' => $networkBanner->uuid,
                            'iid' => $impressionId,
                            'r' => Utils::urlSafeBase64Encode($networkBanner->view_url),
                            'zid' => $zone->uuid,
                        ]
                    )
                ),
                'info_box' => true,
                'rpm' => 0.5,
                'request_id' => '3',
            ]
        ]);
    }
}
