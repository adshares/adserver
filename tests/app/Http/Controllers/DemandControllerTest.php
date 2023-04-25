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

namespace Adshares\Adserver\Tests\Http\Controllers;

use Adshares\Adserver\Http\Utils;
use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\EventLog;
use Adshares\Adserver\Models\Payment;
use Adshares\Adserver\Models\ServeDomain;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\Utilities\AdsAuthenticator;
use Adshares\Adserver\Utilities\DatabaseConfigReader;
use Adshares\Demand\Application\Service\PaymentDetailsVerify;
use DateTimeImmutable;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

use function uniqid;

final class DemandControllerTest extends TestCase
{
    private const PAYMENT_DETAIL_URL = '/payment-details';

    private const INVENTORY_LIST_URL = '/adshares/inventory/list';

    public function testPaymentDetailsWhenMoreThanOnePaymentExistsForGivenTransactionIdAndAddress(): void
    {
        $this->app->bind(
            PaymentDetailsVerify::class,
            function () {
                $signatureVerify = $this->createMock(PaymentDetailsVerify::class);

                $signatureVerify
                    ->expects($this->once())
                    ->method('verify')
                    ->willReturn(true);

                return $signatureVerify;
            }
        );

        $this->login();

        $accountAddress = '0001-00000001-8B4E';
        $accountAddressDifferentUser = '0001-00000002-BB2D';

        $transactionId = '0001:00000001:0001';
        $date = '2018-01-01T10:10:00+00:00';

        $payment1 = Payment::factory()->create(['account_address' => $accountAddress, 'tx_id' => $transactionId]);
        $payment2 = Payment::factory()->create(['account_address' => $accountAddress, 'tx_id' => $transactionId]);
        $payment3 = Payment::factory()->create(['account_address' => $accountAddress, 'tx_id' => $transactionId]);
        $payment4 =
            Payment::factory()->create(
                ['account_address' => $accountAddressDifferentUser, 'tx_id' => $transactionId]
            );
        $payment5 =
            Payment::factory()->create(
                ['account_address' => $accountAddressDifferentUser, 'tx_id' => $transactionId]
            );

        EventLog::factory()->create(['payment_id' => $payment1]);
        EventLog::factory()->create(['payment_id' => $payment1]);
        EventLog::factory()->create(['payment_id' => $payment2]);
        EventLog::factory()->create(['payment_id' => $payment2]);
        EventLog::factory()->create(['payment_id' => $payment3]);
        EventLog::factory()->create(['payment_id' => $payment4]);
        EventLog::factory()->create(['payment_id' => $payment5]);

        $url = sprintf(
            '%s/%s/%s/%s/%s',
            self::PAYMENT_DETAIL_URL,
            $transactionId,
            $accountAddress,
            $date,
            sha1(uniqid())
        );

        $response = $this->getJson($url);
        $content = json_decode($response->getContent());

        $response->assertStatus(Response::HTTP_OK);
        $this->assertCount(5, $content);
    }

    public function testInventoryList(): void
    {
        ServeDomain::factory()->create(['base_url' => 'https://example.com']);
        $user = $this->login();

        /** @var Campaign $campaignDraft */
        $campaignDraft = Campaign::factory()->create(
            [
                'user_id' => $user->id,
                'status' => Campaign::STATUS_DRAFT,
            ]
        );
        /** @var Campaign $campaignInactive */
        $campaignInactive = Campaign::factory()->create(
            [
                'user_id' => $user->id,
                'status' => Campaign::STATUS_INACTIVE,
            ]
        );
        /** @var Campaign $campaignActive */
        $campaignActive = Campaign::factory()->create(
            [
                'user_id' => $user->id,
                'status' => Campaign::STATUS_ACTIVE,
            ]
        );
        /** @var Campaign $campaignSuspended */
        $campaignSuspended = Campaign::factory()->create(
            [
                'user_id' => $user->id,
                'status' => Campaign::STATUS_SUSPENDED,
            ]
        );
        $activeCampaignsCount = 1;

        /** @var Banner $bannerActive */
        $bannerActive = Banner::factory()->create(
            [
                'creative_contents' => 'dummy',
                'campaign_id' => $campaignActive->id,
                'status' => Banner::STATUS_ACTIVE,
            ]
        );
        Banner::factory()->create(['campaign_id' => $campaignActive->id, 'status' => Banner::STATUS_INACTIVE]);
        Banner::factory()->create(['campaign_id' => $campaignDraft->id]);
        Banner::factory()->create(['campaign_id' => $campaignInactive->id]);
        Banner::factory()->create(['campaign_id' => $campaignSuspended->id]);
        $activeBannersCount = 1;

        $response = $this->getJson(self::INVENTORY_LIST_URL);
        $response->assertSuccessful();
        $content = json_decode($response->getContent(), true);

        $this->assertCount($activeCampaignsCount, $content);
        $this->assertEquals($campaignActive->uuid, $content[0]['id']);
        $this->assertCount($activeBannersCount, $content[0]['banners']);
        $this->assertEquals($bannerActive->uuid, $content[0]['banners'][0]['id']);
        $this->assertEquals('829c3804401b0727f70f73d4415e162400cbe57b', $content[0]['banners'][0]['checksum']);
        $this->assertEquals(
            'https://example.com/serve/x' . $bannerActive->uuid . '.doc?v=829c',
            $content[0]['banners'][0]['serve_url']
        );
        $this->assertEquals($campaignActive->medium, $content[0]['medium']);
        $this->assertEquals($campaignActive->vendor, $content[0]['vendor']);
    }

    /**
     * @throws Throwable
     */
    public function testInventoryListWithCdn(): void
    {
        Config::updateAdminSettings([Config::CDN_PROVIDER => 'skynet']);
        ServeDomain::factory()->create(['base_url' => 'https://example.com']);
        $user = $this->login();

        /** @var Campaign $campaignActive */
        $campaignActive = Campaign::factory()->create(
            [
                'user_id' => $user->id,
                'status' => Campaign::STATUS_ACTIVE,
            ]
        );

        /** @var Banner $bannerActive */
        $bannerActive = Banner::factory()->create(
            [
                'creative_sha1' => '829c3804401b0727f70f73d4415e162400cbe57b',
                'creative_contents' => 'dummy',
                'campaign_id' => $campaignActive->id,
                'status' => Banner::STATUS_ACTIVE,
                'cdn_url' => 'https://foo.com/file.png'
            ]
        );

        $response = $this->getJson(self::INVENTORY_LIST_URL);
        $response->assertSuccessful();
        $content = json_decode($response->getContent(), true);

        $this->assertCount(1, $content);
        $this->assertEquals($campaignActive->uuid, $content[0]['id']);
        $this->assertCount(1, $content[0]['banners']);
        $this->assertEquals($bannerActive->uuid, $content[0]['banners'][0]['id']);
        $this->assertEquals('829c3804401b0727f70f73d4415e162400cbe57b', $content[0]['banners'][0]['checksum']);
        $this->assertEquals('https://foo.com/file.png', $content[0]['banners'][0]['serve_url']);

        //change content
        $bannerActive->creative_contents = 'foo content';
        $bannerActive->saveOrFail();

        $response = $this->getJson(self::INVENTORY_LIST_URL);
        $response->assertSuccessful();
        $content = json_decode($response->getContent(), true);

        $this->assertCount(1, $content);
        $this->assertEquals($campaignActive->uuid, $content[0]['id']);
        $this->assertCount(1, $content[0]['banners']);
        $this->assertEquals($bannerActive->uuid, $content[0]['banners'][0]['id']);
        $this->assertEquals('ec097bb2a51eb70410d13bbe94ef0319680accb6', $content[0]['banners'][0]['checksum']);
        $this->assertEquals(
            'https://example.com/serve/x' . $bannerActive->uuid . '.doc?v=ec09',
            $content[0]['banners'][0]['serve_url']
        );
    }

    public function testWhitelistInventoryList(): void
    {
        ServeDomain::factory()->create(['base_url' => 'https://example.com']);
        $user = $this->login();

        /** @var Campaign $campaign */
        $campaign = Campaign::factory()->create(
            [
                'user_id' => $user->id,
                'status' => Campaign::STATUS_ACTIVE,
            ]
        );
        Banner::factory()->create(
            [
                'creative_contents' => 'dummy',
                'campaign_id' => $campaign->id,
                'status' => Banner::STATUS_ACTIVE,
            ]
        );

        /** @var AdsAuthenticator $authenticator */
        $authenticator = $this->app->make(AdsAuthenticator::class);

        Config::updateAdminSettings([Config::INVENTORY_EXPORT_WHITELIST => '0001-00000002-BB2D']);

        $response = $this->getJson(self::INVENTORY_LIST_URL);
        $response->assertStatus(401);

        $response = $this->getJson(
            self::INVENTORY_LIST_URL,
            [
                'Authorization' => $authenticator->getHeader(
                    config('app.adshares_address'),
                    Crypt::decryptString(config('app.adshares_secret'))
                )
            ]
        );
        $response->assertStatus(403);

        Config::updateAdminSettings([Config::INVENTORY_EXPORT_WHITELIST => '0001-00000003-AB0C,0001-00000005-CBCA']);
        $response = $this->getJson(
            self::INVENTORY_LIST_URL,
            [
                'Authorization' => $authenticator->getHeader(
                    config('app.adshares_address'),
                    Crypt::decryptString(config('app.adshares_secret'))
                )
            ]
        );
        $response->assertSuccessful();
        $content = json_decode($response->getContent(), true);
        $this->assertCount(1, $content);
    }

    public function testServeDeletedBanner(): void
    {
        $user = $this->login();
        /** @var Campaign $campaign */
        $campaign = Campaign::factory()->create(
            [
                'user_id' => $user->id,
                'status' => Campaign::STATUS_ACTIVE,
                'deleted_at' => new DateTimeImmutable(),
            ]
        );
        /** @var Banner $banner */
        $banner = Banner::factory()->create([
            'campaign_id' => $campaign->id,
            'deleted_at' => new DateTimeImmutable(),
        ]);

        $response = self::getJson(self::buildServeUri($banner->uuid));

        $response->assertStatus(404);
    }

    public function testContext(): void
    {
        Config::updateAdminSettings([Config::SERVE_BASE_URL => 'https://adshares.net']);
        DatabaseConfigReader::overwriteAdministrationConfig();
        /** @var EventLog $event */
        $event = EventLog::factory()->create();

        ob_start();
        $response = self::getJson(self::buildContextUri($event->event_id));
        ob_get_clean();

        $response->assertStatus(Response::HTTP_OK);
        self::assertDatabaseHas(
            EventLog::class,
            [
                'event_id' => hex2bin($event->event_id),
                'domain' => 'example.com',
            ],
        );
    }

    public function testClick(): void
    {
        [$query, $banner] = $this->initBeforeClickEvent();

        ob_start();
        $response = $this->get(self::buildClickUri($banner->uuid, $query));
        ob_get_clean();

        $response->assertStatus(Response::HTTP_FOUND);
        self::assertDatabaseHas(EventLog::class, [
            'case_id' => hex2bin('13245679801324567980132456798012'),
            'event_id' => hex2bin('13245679801324567980132456798003'),
            'event_type' => EventLog::TYPE_CLICK,
            'pay_to' => hex2bin('000100000005'),
            'publisher_id' => hex2bin('11111111111111111111111111111111'),
        ]);
    }

    public function testClickWhenCaseIdAndImpressionIdAndPublisherIdAreUuid(): void
    {
        [$query, $banner] = $this->initBeforeClickEvent();
        $query['cid'] = '13245679-8013-2456-7980-132456798012';
        $query['iid'] = '52511506-5f3d-4338-8d57-57a2d5c4b0e8';
        $query['pid'] = '9231200a-10f5-48dc-a74b-2ad767bb713d';

        ob_start();
        $response = $this->get(self::buildClickUri($banner->uuid, $query));
        ob_get_clean();

        $response->assertStatus(Response::HTTP_FOUND);
        self::assertDatabaseHas(EventLog::class, [
            'case_id' => hex2bin('13245679801324567980132456798012'),
            'event_id' => hex2bin('13245679801324567980132456798003'),
            'event_type' => EventLog::TYPE_CLICK,
            'pay_to' => hex2bin('000100000005'),
            'publisher_id' => hex2bin('9231200a10f548dca74b2ad767bb713d'),
        ]);
    }

    public function testClickWithoutViewEvent(): void
    {
        [$query, $banner] = $this->initBeforeViewEvent();

        ob_start();
        $response = $this->get(self::buildClickUri($banner->uuid, $query));
        ob_get_clean();

        $response->assertStatus(Response::HTTP_FOUND);
        self::assertDatabaseEmpty(EventLog::class);
    }

    public function testClickFailWhileMissingCaseId(): void
    {
        [$query, $banner] = $this->initBeforeClickEvent();
        unset($query['cid']);

        $response = $this->get(self::buildClickUri($banner->uuid, $query));

        $response->assertStatus(Response::HTTP_BAD_REQUEST);
    }

    public function testClickFailWhileCaseIdIsInvalid(): void
    {
        [$query, $banner] = $this->initBeforeClickEvent();
        $query['cid'] = true;

        $response = $this->get(self::buildClickUri($banner->uuid, $query));

        $response->assertStatus(Response::HTTP_BAD_REQUEST);
    }

    public function testView(): void
    {
        [$query, $banner] = $this->initBeforeViewEvent();

        ob_start();
        $response = $this->get(self::buildViewUri($banner->uuid, $query));
        ob_get_clean();

        $response->assertStatus(Response::HTTP_OK);
        self::assertDatabaseHas(EventLog::class, [
            'case_id' => hex2bin('13245679801324567980132456798012'),
            'event_id' => hex2bin('13245679801324567980132456798002'),
            'event_type' => EventLog::TYPE_VIEW,
            'pay_to' => hex2bin('000100000005'),
            'publisher_id' => hex2bin('11111111111111111111111111111111'),
        ]);
    }

    public function testViewWhenCaseIdAndImpressionIdAndPublisherIdAreUuid(): void
    {
        [$query, $banner] = $this->initBeforeViewEvent();
        $query['cid'] = '13245679-8013-2456-7980-132456798012';
        $query['iid'] = '52511506-5f3d-4338-8d57-57a2d5c4b0e8';
        $query['pid'] = '9231200a-10f5-48dc-a74b-2ad767bb713d';

        ob_start();
        $response = $this->get(self::buildViewUri($banner->uuid, $query));
        ob_get_clean();

        $response->assertStatus(Response::HTTP_OK);
        self::assertDatabaseHas(EventLog::class, [
            'case_id' => hex2bin('13245679801324567980132456798012'),
            'event_id' => hex2bin('13245679801324567980132456798002'),
            'event_type' => EventLog::TYPE_VIEW,
            'pay_to' => hex2bin('000100000005'),
            'publisher_id' => hex2bin('9231200a10f548dca74b2ad767bb713d'),
        ]);
    }

    public function testViewJson(): void
    {
        Config::updateAdminSettings([Config::ADUSER_BASE_URL => 'https://aduser.example.com']);
        [$query, $banner] = $this->initBeforeViewEvent();

        ob_start();
        $response = $this->get(
            self::buildViewUri($banner->uuid, $query),
            ['Accept' => 'application/json'],
        );
        ob_get_clean();

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(['context' => []]);
        $response->assertJsonCount(2, 'context');
        self::assertDatabaseHas(EventLog::class, [
            'case_id' => hex2bin('13245679801324567980132456798012'),
            'event_id' => hex2bin('13245679801324567980132456798002'),
            'event_type' => EventLog::TYPE_VIEW,
            'pay_to' => hex2bin('000100000005'),
            'publisher_id' => hex2bin('11111111111111111111111111111111'),
        ]);
    }

    public function testViewFailWhileMissingCaseId(): void
    {
        [$query, $banner] = $this->initBeforeViewEvent();
        unset($query['cid']);

        $response = $this->get(self::buildViewUri($banner->uuid, $query));

        $response->assertStatus(Response::HTTP_BAD_REQUEST);
    }

    public function testViewFailWhileCaseIdIsInvalid(): void
    {
        [$query, $banner] = $this->initBeforeViewEvent();
        $query['cid'] = true;

        $response = $this->get(self::buildViewUri($banner->uuid, $query));

        $response->assertStatus(Response::HTTP_BAD_REQUEST);
        self::assertDatabaseEmpty(EventLog::class);
    }

    public function testInitContext(): void
    {
        /** @var EventLog $event */
        $event = EventLog::factory()->create();

        $response = $this->get(sprintf('/init-context/%s', $event->event_id));

        $response->assertStatus(Response::HTTP_OK);
        self::assertStringStartsWith('text/html', $response->headers->get('Content-type'));
    }

    private function initBeforeClickEvent(): array
    {
        $arr = $this->initBeforeViewEvent();
        EventLog::factory()->create([
            'event_id' => Utils::createCaseIdContainingEventType($arr[0]['cid'], EventLog::TYPE_VIEW),
            'event_type' => EventLog::TYPE_VIEW,
        ]);
        return $arr;
    }

    private function initBeforeViewEvent(): array
    {
        /** @var Banner $banner */
        $banner = Banner::factory()->create();
        $query = [
            'cid' => '13245679801324567980132456798012',
            'ctx' => '',
            'pid' => '11111111111111111111111111111111',
            'pto' => '0001-00000005-CBCA',
        ];
        return [$query, $banner];
    }

    private static function buildServeUri(string $uuid): string
    {
        return sprintf('/serve/%s', $uuid);
    }

    private static function buildClickUri(string $bannerId, ?array $query = null): string
    {
        $uri = sprintf('/click/%s', $bannerId);
        if (null !== $query) {
            $uri .= '?' . http_build_query($query);
        }
        return $uri;
    }

    private static function buildViewUri(string $bannerId, ?array $query = null): string
    {
        $uri = sprintf('/view/%s', $bannerId);
        if (null !== $query) {
            $uri .= '?' . http_build_query($query);
        }
        return $uri;
    }

    private static function buildContextUri(string $uuid): string
    {
        return sprintf('/context/%s', $uuid);
    }
}
