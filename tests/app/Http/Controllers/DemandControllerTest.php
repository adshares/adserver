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

namespace Adshares\Adserver\Tests\Http\Controllers;

use Adshares\Adserver\Http\Utils;
use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\ConversionDefinition;
use Adshares\Adserver\Models\EventLog;
use Adshares\Adserver\Models\Payment;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Demand\Application\Service\PaymentDetailsVerify;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use function uniqid;

final class DemandControllerTest extends TestCase
{
    use RefreshDatabase;

    private const PAYMENT_DETAIL_URL = '/payment-details';

    private const INVENTORY_LIST_URL = '/adshares/inventory/list';

    private const CONVERSION_URL_TEMPLATE = '/conversion/{uuid}.gif';

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

        $user = factory(User::class)->create();
        $user->is_advertiser = 1;
        $this->actingAs($user, 'api');

        $accountAddress = '0001-00000001-0001';
        $accountAddressDifferentUser = '0001-00000002-0001';

        $transactionId = '0001:00000001:0001';
        $date = '2018-01-01T10:10:00+00:00';

        $payment1 = factory(Payment::class)->create(['account_address' => $accountAddress, 'tx_id' => $transactionId]);
        $payment2 = factory(Payment::class)->create(['account_address' => $accountAddress, 'tx_id' => $transactionId]);
        $payment3 = factory(Payment::class)->create(['account_address' => $accountAddress, 'tx_id' => $transactionId]);
        $payment4 =
            factory(Payment::class)->create(
                ['account_address' => $accountAddressDifferentUser, 'tx_id' => $transactionId]
            );
        $payment5 =
            factory(Payment::class)->create(
                ['account_address' => $accountAddressDifferentUser, 'tx_id' => $transactionId]
            );

        factory(EventLog::class)->create(['payment_id' => $payment1]);
        factory(EventLog::class)->create(['payment_id' => $payment1]);
        factory(EventLog::class)->create(['payment_id' => $payment2]);
        factory(EventLog::class)->create(['payment_id' => $payment2]);
        factory(EventLog::class)->create(['payment_id' => $payment3]);
        factory(EventLog::class)->create(['payment_id' => $payment4]);
        factory(EventLog::class)->create(['payment_id' => $payment5]);

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
        $user = factory(User::class)->create();
        $user->is_advertiser = 1;
        $this->actingAs($user, 'api');

        $campaignDraft = factory(Campaign::class)->create(['user_id' => $user->id, 'status' => Campaign::STATUS_DRAFT]);
        $campaignInactive =
            factory(Campaign::class)->create(['user_id' => $user->id, 'status' => Campaign::STATUS_INACTIVE]);
        $campaignActive =
            factory(Campaign::class)->create(['user_id' => $user->id, 'status' => Campaign::STATUS_ACTIVE]);
        $campaignSuspended =
            factory(Campaign::class)->create(['user_id' => $user->id, 'status' => Campaign::STATUS_SUSPENDED]);
        $activeCampaignsCount = 1;

        $bannerActive =
            factory(Banner::class)->create(['campaign_id' => $campaignActive->id, 'status' => Banner::STATUS_ACTIVE]);
        factory(Banner::class)->create(['campaign_id' => $campaignActive->id, 'status' => Banner::STATUS_INACTIVE]);
        factory(Banner::class)->create(['campaign_id' => $campaignDraft->id]);
        factory(Banner::class)->create(['campaign_id' => $campaignInactive->id]);
        factory(Banner::class)->create(['campaign_id' => $campaignSuspended->id]);
        $activeBannersCount = 1;

        $response = $this->getJson(self::INVENTORY_LIST_URL);
        $content = json_decode($response->getContent(), true);

        $this->assertCount($activeCampaignsCount, $content);
        $this->assertEquals($campaignActive->uuid, $content[0]['id']);
        $this->assertCount($activeBannersCount, $content[0]['banners']);
        $this->assertEquals($bannerActive->uuid, $content[0]['banners'][0]['id']);
    }

    public function testConversion(): void
    {
//        $this->disableCookiesEncryption('tid');

        $user = factory(User::class)->create();
        $this->actingAs($user, 'api');

        /** @var Campaign $campaign */
        $campaign = factory(Campaign::class)->create(
            [
                'user_id' => $user->id,
                'budget' => 100000000000,
            ]
        );

        /** @var EventLog $event */
        $event = factory(EventLog::class)->create(
            [
                'event_type' => EventLog::TYPE_VIEW,
                'campaign_id' => $campaign->uuid,
            ]
        );
        $event->event_id = Utils::createCaseIdContainingEventType($event->case_id, EventLog::TYPE_VIEW);
        $event->save();

        $conversionValue = 100000000;
        $conversion = new ConversionDefinition();
        $conversion->campaign_id = $campaign->id;
        $conversion->name = 'a';
        $conversion->budget_type = 'in_budget';
        $conversion->event_type = 'Purchase';
        $conversion->type = ConversionDefinition::BASIC_TYPE;
        $conversion->value = $conversionValue;

        $conversion->save();

        $url = $this->buildConversionUrl($conversion->uuid);

        $cookies = [
            'tid' => Utils::urlSafeBase64Encode(hex2bin($event->tracking_id)),
        ];
        $response = $this->call('get', $url, [], $cookies);

        $response->assertStatus(Response::HTTP_OK);

        $conversionEvent = EventLog::where('event_type', EventLog::TYPE_CONVERSION)->first();
        $eventData = [
            'event_type' => EventLog::TYPE_CONVERSION,
            'campaign_id' => hex2bin($campaign->uuid),
        ];
        $this->assertDatabaseHas('event_logs', $eventData);

        $conversionGroupData = [
            'event_logs_id' => $conversionEvent->id,
            'conversion_definition_id' => $conversion->id,
            'value' => $conversionValue,
            'weight' => 1,
        ];
        $this->assertDatabaseHas('conversion_groups', $conversionGroupData);
    }

    private function buildConversionUrl(string $uuid): string
    {
        return str_replace('{uuid}', $uuid, self::CONVERSION_URL_TEMPLATE);
    }

    protected function disableCookiesEncryption($name)
    {
        $this->app->resolving(
            EncryptCookies::class,
            function ($object) use ($name) {
                /** @var EncryptCookies $object */
                $object->disableFor($name);
            }
        );

        return $this;
    }
}
