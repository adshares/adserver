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

namespace Adshares\Adserver\Tests\Http;

use Adshares\Adserver\Models\User;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Advertiser\Dto\ChartInput;
use Adshares\Tests\Advertiser\Repository\DummyStatsRepository;
use DateTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;

final class StatsControllerTest extends TestCase
{
    use RefreshDatabase;

    private const ADVERTISER_CHART_URI = '/api/advertiser/stats/chart';

    public function testAdvertiserChartWhenViewTypeAndHourResolutionAndDateEndIsEarlierThanDateStart(): void
    {
        $user = factory(User::class)->create();
        $user->is_advertiser = 1;
        $this->actingAs($user, 'api');

        $dateStart = (new DateTime())->format(DateTime::ATOM);
        $dateEnd = (new DateTime())->modify('-1 hour')->format(DateTime::ATOM);
        $url = sprintf('%s/view/hour/%s/%s', self::ADVERTISER_CHART_URI, $dateStart, $dateEnd);
        $response = $this->getJson($url);

        $response->assertStatus(Response::HTTP_BAD_REQUEST);
    }

    /**
     * @param string $type
     * @param array $resolutions
     *
     * @throws \Exception
     * @dataProvider providerDataForAdvertiserChart
     */
    public function testAdvertiserChartWhenViewTypeAndHourResolution(string $type, array $resolutions): void
    {
        $repository = new DummyStatsRepository();
        $user = factory(User::class)->create();
        $user->is_advertiser = 1;
        $this->actingAs($user, 'api');

        $dateStart = (new DateTime());
        $dateEnd = (new DateTime());

        foreach ($resolutions as $resolution) {
            $url = sprintf(
                '%s/%s/%s/%s/%s',
                self::ADVERTISER_CHART_URI,
                $type,
                $resolution,
                $dateStart->format(DateTime::ATOM),
                $dateEnd->format(DateTime::ATOM)
            );

            $methodNameMapper = [
                ChartInput::CLICK_TYPE => 'fetchClick',
                ChartInput::VIEW_TYPE => 'fetchView',
                ChartInput::CPC_TYPE => 'fetchCpc',
                ChartInput::CPM_TYPE => 'fetchCpm',
                ChartInput::SUM_TYPE => 'fetchSum',
                ChartInput::CTR_TYPE => 'fetchCtr',
            ];

            $method = $methodNameMapper[$type];

            $response = $this->getJson($url);
            $response->assertStatus(Response::HTTP_OK);
            $response->assertJson($repository->$method($user->id, $resolution, $dateStart, $dateEnd)->getData());
        }
    }

    public function providerDataForAdvertiserChart(): array
    {
        return [
            ['view', ['hour', 'day', 'week', 'month', 'quarter', 'year']],
            ['click', ['hour', 'day', 'week', 'month', 'quarter', 'year']],
            ['cpc', ['hour', 'day', 'week', 'month', 'quarter', 'year']],
            ['cpm', ['hour', 'day', 'week', 'month', 'quarter', 'year']],
            ['sum', ['hour', 'day', 'week', 'month', 'quarter', 'year']],
            ['ctr', ['hour', 'day', 'week', 'month', 'quarter', 'year']],
        ];
    }
}
