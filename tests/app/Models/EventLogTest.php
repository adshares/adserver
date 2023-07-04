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

namespace Adshares\Adserver\Tests\Models;

use Adshares\Adserver\Models\EventLog;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Supply\Application\Dto\UserContext;

class EventLogTest extends TestCase
{
    public function testCreateEventLogValid(): void
    {
        $data = $this->getEventData();

        EventLog::create(
            $data['case_id'],
            $data['event_id'],
            $data['banner_id'],
            $data['zone_id'],
            $data['tracking_id'],
            $data['publisher_id'],
            $data['campaign_id'],
            $data['advertiser_id'],
            $data['pay_to'],
            $data['impression_context'],
            $data['their_userdata'],
            $data['event_type'],
            $data['medium'],
            $data['vendor'],
        );

        $theirContext = json_encode($data['impression_context']);
        unset($data['impression_context']);

        $this->assertEquals(1, EventLog::count());
        $event = EventLog::first()->toArray();

        foreach ($data as $key => $value) {
            $this->assertArrayHasKey($key, $event);
            $this->assertEquals($value, $event[$key]);
        }
        $this->assertArrayHasKey('their_context', $event);
        $this->assertEquals($theirContext, json_encode($event['their_context']));
    }

    public function testCreateEventLogTryTwoSame(): void
    {
        $data = $this->getEventData();

        for ($i = 0; $i < 2; $i++) {
            EventLog::create(
                $data['case_id'],
                $data['event_id'],
                $data['banner_id'],
                $data['zone_id'],
                $data['tracking_id'],
                $data['publisher_id'],
                $data['campaign_id'],
                $data['advertiser_id'],
                $data['pay_to'],
                $data['impression_context'],
                $data['their_userdata'],
                $data['event_type'],
                $data['medium'],
                $data['vendor'],
            );
        }

        $this->assertEquals(1, EventLog::count());
    }

    public function testCreateEventLogInvalidImpressionContext(): void
    {
        $data = $this->getEventData();
        $data['impression_context']['device']['headers']['binary'] = ["\xB1\x31"];

        $this->expectException(RuntimeException::class);

        EventLog::create(
            $data['case_id'],
            $data['event_id'],
            $data['banner_id'],
            $data['zone_id'],
            $data['tracking_id'],
            $data['publisher_id'],
            $data['campaign_id'],
            $data['advertiser_id'],
            $data['pay_to'],
            $data['impression_context'],
            $data['their_userdata'],
            $data['event_type'],
            $data['medium'],
            $data['vendor'],
        );
    }

    public function testCreateWithUserData(): void
    {
        $data = array_merge(
            $this->getEventData(),
            [
                'human_score' => 0.5,
                'page_rank' => 0.7,
                'our_userdata' => null,
            ]
        );

        EventLog::createWithUserData(
            $data['case_id'],
            $data['event_id'],
            $data['banner_id'],
            $data['zone_id'],
            $data['tracking_id'],
            $data['publisher_id'],
            $data['campaign_id'],
            $data['advertiser_id'],
            $data['pay_to'],
            $data['impression_context'],
            $data['their_userdata'],
            $data['event_type'],
            $data['medium'],
            $data['vendor'],
            $data['human_score'],
            $data['page_rank'],
            $data['our_userdata'],
        );

        $theirContext = json_encode($data['impression_context']);
        unset($data['impression_context']);

        $this->assertEquals(1, EventLog::count());
        $event = EventLog::first()->toArray();

        foreach ($data as $key => $value) {
            $this->assertArrayHasKey($key, $event);
            $this->assertEquals($value, $event[$key]);
        }
        $this->assertArrayHasKey('their_context', $event);
        $this->assertEquals($theirContext, json_encode($event['their_context']));
    }

    public function getEventData(): array
    {
        return [
            'case_id' => '21aa0a660ab4aec7c18b940e8299f500',
            'event_id' => '21aa0a660ab4aec7c18b940e8299f502',
            'banner_id' => '4f4f599580054d8d9ef8a09b6681b54a',
            'zone_id' => '950a658cacb246f8a142efb722a2acd1',
            'tracking_id' => '4f53763d6653674b6d87eaf17951f199',
            'publisher_id' => '81d94be9e33148bfb417313c1d010389',
            'campaign_id' => '44e003216ee546cd8585827ba43ef523',
            'advertiser_id' => '100130722f8b4ab180523e70d3ff8c45',
            'pay_to' => '0002-00000008-F4B5',
            'impression_context' => [
                'site' => [
                    'domain' => 'example.com',
                    'inframe' => 'no',
                    'page' => 'http://example.com/test.html',
                    'keywords' => [0 => '',],
                    'referrer' => '',
                    'popup' => 0,
                ],
                'device' => [
                    'ua' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:68.0) Gecko/20100101 Firefox/68.0',
                    'ip' => '172.25.0.1',
                    'ips' => [0 => '172.25.0.1',],
                    'headers' => [
                        'upgrade-insecure-requests' => [0 => '1',],
                        'cookie' => [0 => 'tid=T1N2PWZTZ0tth-rxeVHxmY4bbKJJCg',],
                        'connection' => [0 => 'keep-alive',],
                        'referer' => [0 => 'http://example.com/test.html',],
                        'accept-encoding' => [0 => 'gzip, deflate',],
                        'accept-language' => [0 => 'en-US,en;q=0.5',],
                        'accept' => [0 => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',],
                        'user-agent' => [
                            0 => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:68.0) Gecko/20100101 Firefox/68.0',
                        ],
                        'host' => [0 => 'example.com',],
                        'content-length' => [0 => '',],
                        'content-type' => [0 => '',],
                    ],
                ],
            ],
            'their_userdata' => '',
            'event_type' => 'view',
            'medium' => 'web',
            'vendor' => null,
        ];
    }

    /**
     * @dataProvider getDomainFromContextProvider
     */
    public function testGetDomainFromContext(?string $expectedDomain, array $context): void
    {
        self::assertEquals($expectedDomain, EventLog::getDomainFromContext($context));
    }

    public function getDomainFromContextProvider(): array
    {
        return [
            ['adshares.net', ['site' => ['domain' => 'adshares.net']]],
            [null, ['site' => ['domain' => 'example.com']]],
            [null, ['site' => []]],
            [null, []],
        ];
    }

    public function testUpdateWithUserContext(): void
    {
        $userContext = UserContext::fromAdUserArray(
            [
                'human_score' => 0.44,
                'uuid' => '00000000000000000000000000000001',
            ]
        );
        /** @var EventLog $eventLog */
        $eventLog = EventLog::factory()->create();
        $eventLog->domain = 'scene-0-0.decentraland.org';
        $eventLog->medium = 'metaverse';
        $eventLog->saveOrFail();

        $eventLog->updateWithUserContext($userContext);

        self::assertEquals(0, $eventLog->human_score);
    }
}
