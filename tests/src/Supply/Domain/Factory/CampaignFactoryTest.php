<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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

namespace Adshares\Test\Supply\Domain\Factory;

use Adshares\Common\Domain\ValueObject\Uuid;
use Adshares\Supply\Domain\Factory\CampaignFactory;
use Adshares\Supply\Domain\Factory\Exception\InvalidCampaignArgumentException;
use DateTime;

final class CampaignFactoryTest
{
    public function testCreateFromArray()
    {
        $this->expectException(InvalidCampaignArgumentException::class);

        $data = [
            'id' => 1,
            'uuid' => Uuid::v4(),
            'user_id' => 1,
            'landing_url' => 'http://adshares.pl',
            'date_start' => (new DateTime())->modify('-1 day'),
            'date_end' => (new DateTime())->modify('+2 days'),
            'source_host' => [
                'host' => 'localhost:8101',
                'address' => '0001-00000001-0001',
                'created_at' => (new DateTime())->modify('-1 days'),
                'updated_at' => (new DateTime())->modify('-1 days'),
            ],
            'banners' => [
                [
                    'serve_url' => 'http://localhost:8101/serve/1',
                    'click_url' => 'http://localhost:8101/click/1',
                    'view_url' => 'http://localhost:8101/view/1',
                    'type' => 'image',
                    'width' => 728,
                    'height' => 90,
                ],
                [
                    'serve_url' => 'http://localhost:8101/serve/1',
                    'click_url' => 'http://localhost:8101/click/1',
                    'view_url' => 'http://localhost:8101/view/1',
                    'type' => 'image',
                    'width' => 728,
                    'height' => 90,
                ],
                [
                    'serve_url' => 'http://localhost:8101/serve/1',
                    'click_url' => 'http://localhost:8101/click/1',
                    'view_url' => 'http://localhost:8101/view/1',
                    'type' => 'image',
                    'width' => 728,
                    'height' => 90,
                ],
            ],
            'max_cpc' => 1,
            'max_cpm' => 1,
            'budget' => 10,
            'demand_host' => 'localhost:8101',
            'targeting_excludes' => [],
            'targeting_requires' => [],
        ];

        CampaignFactory::createFromArray($data);
    }
}
