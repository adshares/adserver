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
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\ConversionDefinition;
use Adshares\Adserver\Models\EventConversionLog;
use Adshares\Adserver\Models\EventLog;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;

final class ConversionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function testConversion(): void
    {
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

        $conversionEvent = EventConversionLog::where('event_type', EventLog::TYPE_CONVERSION)->first();
        $eventData = [
            'event_type' => EventConversionLog::TYPE_CONVERSION,
            'campaign_id' => hex2bin($campaign->uuid),
        ];
        $this->assertDatabaseHas('event_conversion_logs', $eventData);

        $conversionGroupData = [
            'event_logs_id' => $conversionEvent->id,
            'conversion_definition_id' => $conversion->id,
            'value' => $conversionValue,
            'weight' => 1,
        ];
        $this->assertDatabaseHas('conversions', $conversionGroupData);
    }

    private function buildConversionUrl(string $uuid): string
    {
        return route('conversion.gif', ['{uuid}' => $uuid]);
    }
}
