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

namespace Adshares\Adserver\Tests\Http\Requests\Campaign;

use Adshares\Adserver\Http\Requests\Campaign\CampaignTargetingProcessor;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\Utilities\DatabaseConfigReader;
use Adshares\Common\Exception\InvalidArgumentException;
use Adshares\Mock\Repository\DummyConfigurationRepository;

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
        $processor = new CampaignTargetingProcessor((new DummyConfigurationRepository())->fetchMedium());

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

    /**
     * @dataProvider processTargetingInvalidSiteDomain
     */
    public function testProcessTargetingRequireInvalidSiteDomain(string $medium, ?string $vendor, string $domain): void
    {
        $processor = new CampaignTargetingProcessor(
            (new DummyConfigurationRepository())->fetchMedium($medium, $vendor)
        );
        $targeting = [
            'site' => [
                'domain' => [
                    $domain,
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);

        $processor->processTargetingRequire($targeting);
    }

    public function processTargetingInvalidSiteDomain(): array
    {
        return [
            ['web', null, '1'],
            ['metaverse', 'decentraland', 'example.com'],
            ['metaverse', 'decentraland', 'scene-3.decentraland.org'],
            ['metaverse', 'cryptovoxels', 'example.com'],
        ];
    }

    /**
     * @dataProvider processTargetingValidSiteDomain
     */
    public function testProcessTargetingRequireValidSiteDomain(string $medium, ?string $vendor, string $domain): void
    {
        $processor = new CampaignTargetingProcessor(
            (new DummyConfigurationRepository())->fetchMedium($medium, $vendor)
        );
        $targeting = [
            'site' => [
                'domain' => [
                    $domain,
                ],
            ],
        ];

        $result = $processor->processTargetingRequire($targeting);

        $this->assertEquals([$domain], $result['site']['domain']);
    }

    public function processTargetingValidSiteDomain(): array
    {
        return [
            ['web', null, 'example.com'],
            ['metaverse', 'decentraland', 'decentraland.org'],
            ['metaverse', 'decentraland', 'scene-3-n7.decentraland.org'],
            ['metaverse', 'cryptovoxels', 'cryptovoxels.com'],
            ['metaverse', 'cryptovoxels', 'scene-3.cryptovoxels.com'],
        ];
    }
}
