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
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\ConversionDefinition;
use Adshares\Adserver\Models\EventLog;
use Adshares\Adserver\Tests\TestCase;
use Symfony\Component\HttpFoundation\Response;

final class ConversionControllerTest extends TestCase
{
    public function testConversion(): void
    {
        /** @var Campaign $campaign */
        $campaign = Campaign::factory()->create(['budget' => 100_000_000_000]);

        /** @var EventLog $event */
        $event = EventLog::factory()->create(
            [
                'event_type' => EventLog::TYPE_VIEW,
                'campaign_id' => $campaign->uuid,
            ]
        );
        $event->event_id = Utils::createCaseIdContainingEventType($event->case_id, EventLog::TYPE_VIEW);
        $event->save();

        $conversionValue = 100000000;
        $conversionDefinition = new ConversionDefinition();
        $conversionDefinition->campaign_id = $campaign->id;
        $conversionDefinition->name = 'a';
        $conversionDefinition->limit_type = 'in_budget';
        $conversionDefinition->event_type = 'Purchase';
        $conversionDefinition->type = ConversionDefinition::BASIC_TYPE;
        $conversionDefinition->value = $conversionValue;
        $conversionDefinition->save();

        $url = $this->buildConversionUrl($conversionDefinition->uuid);

        $cookies = [
            'tid' => Utils::urlSafeBase64Encode(hex2bin($event->tracking_id)),
        ];
        ob_start();
        $response = $this->call('GET', $url, [], $cookies);
        ob_get_clean();

        $response->assertStatus(Response::HTTP_OK);

        $conversionData = [
            'event_logs_id' => $event->id,
            'conversion_definition_id' => $conversionDefinition->id,
            'value' => $conversionValue,
            'weight' => 1,
        ];
        $this->assertDatabaseHas('conversions', $conversionData);
    }

    public function testConversionClick(): void
    {
        /** @var Campaign $campaign */
        $campaign = Campaign::factory()->create([
            'budget' => 100_000_000_000,
            'conversion_click' => 1,
        ]);
        /** @var EventLog $event */
        $event = EventLog::factory()->create(
            [
                'campaign_id' => $campaign->uuid,
                'case_id' => '0123456789abcdef0123456789abcdef',
                'event_type' => EventLog::TYPE_VIEW,
            ]
        );
        $event->event_id = Utils::createCaseIdContainingEventType($event->case_id, EventLog::TYPE_VIEW);
        $event->save();

        ob_start();
        $response = $this->getJson(self::buildConversionClickUri(
            $campaign,
            ['cid' => '0123456789abcdef0123456789abcdef'],
        ));
        ob_get_clean();

        $response->assertStatus(Response::HTTP_OK);
        self::assertEquals(1, $event->refresh()->is_view_clicked);
        self::assertDatabaseHas(EventLog::class, [
            'campaign_id' => hex2bin($campaign->uuid),
            'case_id' => hex2bin('0123456789abcdef0123456789abcdef'),
            'event_type' => EventLog::TYPE_CLICK,
        ]);
    }

    public function testConversionClickWhileNoViewEvent(): void
    {
        /** @var Campaign $campaign */
        $campaign = Campaign::factory()->create([
            'budget' => 100_000_000_000,
            'conversion_click' => 1,
        ]);

        $response = $this->getJson(self::buildConversionClickUri(
            $campaign,
            ['cid' => '0123456789abcdef0123456789abcdef'],
        ));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testConversionClickWhileMissingCid(): void
    {
        /** @var Campaign $campaign */
        $campaign = Campaign::factory()->create([
            'budget' => 100_000_000_000,
            'conversion_click' => 1,
        ]);

        $response = $this->getJson(self::buildConversionClickUri($campaign));

        $response->assertStatus(Response::HTTP_BAD_REQUEST);
    }

    private function buildConversionUrl(string $uuid): string
    {
        return route('conversion.gif', ['uuid' => $uuid]);
    }

    private static function buildConversionClickUri(Campaign $campaign, ?array $query = null): string
    {
        $uri = '/kw/kl/' . $campaign->uuid;
        if (null !== $query) {
            $uri .= '?' . http_build_query($query);
        }
        return $uri;
    }
}
