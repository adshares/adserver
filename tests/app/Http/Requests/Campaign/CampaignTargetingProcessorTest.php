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

namespace Adshares\Adserver\Tests\Http\Requests\Campaign;

use Adshares\Adserver\Http\Requests\Campaign\CampaignTargetingProcessor;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\Utilities\DatabaseConfigReader;
use Adshares\Common\Application\Dto\TaxonomyV2\Medium;
use Adshares\Common\Application\Service\ConfigurationRepository;

final class CampaignTargetingProcessorTest extends TestCase
{
    public function testProcessTargetingRequireWhileSetInConfig(): void
    {
        Config::updateAdminSettings(
            [
                Config::CAMPAIGN_TARGETING_REQUIRE => '{"site": {"quality": ["high"]}}',
            ]
        );
        DatabaseConfigReader::overwriteAdministrationConfig();
        $processor = new CampaignTargetingProcessor($this->getTargetingSchema());

        $targeting = [
            'site' => [
                'quality' => [
                    'high',
                    'medium',
                ],
            ],
        ];

        $result = $processor->processTargetingRequire($targeting);

        $this->assertEquals($targeting, $result);
    }

    private function getTargetingSchema(): Medium
    {
        return $this->app->make(ConfigurationRepository::class)->fetchMedium();
    }
}
