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

declare(strict_types=1);

namespace Adshares\Adserver\Tests\Http\Controllers;

use Adshares\Adserver\Http\Utils;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\ConversionDefinition;
use Adshares\Adserver\Models\EventLog;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Tests\TestCase;
use Symfony\Component\HttpFoundation\Response;

final class ConversionControllerTest extends TestCase
{
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

    private function buildConversionUrl(string $uuid): string
    {
        return route('conversion.gif', ['{uuid}' => $uuid]);
    }
}
