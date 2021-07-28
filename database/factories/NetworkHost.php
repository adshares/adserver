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

use Adshares\Adserver\Models\NetworkHost;
use Adshares\Common\Domain\ValueObject\AccountId;
use Adshares\Common\Domain\ValueObject\Email;
use Adshares\Common\Domain\ValueObject\Url;
use Adshares\Supply\Application\Dto\Info;
use Adshares\Supply\Application\Dto\InfoStatistics;
use Faker\Generator as Faker;

$factory->define(
    NetworkHost::class,
    function (Faker $faker) {
        $addresses = [
            AccountId::fromIncompleteString('0001-00000001'),
            AccountId::fromIncompleteString('0001-00000002'),
            AccountId::fromIncompleteString('0001-00000003'),
            AccountId::fromIncompleteString('0001-00000004'),
            AccountId::fromIncompleteString('0001-00000005'),
            AccountId::fromIncompleteString('0001-00000006'),
            AccountId::fromIncompleteString('0001-00000007'),
            AccountId::fromIncompleteString('0001-00000008'),
        ];

        $host = 'https://'.parse_url($faker->url, PHP_URL_HOST);
        $info = new Info(
            'adserver',
            $faker->domainWord,
            '0.1',
            new Url($host),
            new Url($host.':4200'),
            new Url($host.'/policies/privacy.html'),
            new Url($host.'/policies/terms.html'),
            new Url($host.'/adshares/inventory/list'),
            new AccountId('0001-00000004-DBEB'),
            new Email($faker->companyEmail),
            ['PUB', 'ADV'],
            'public'
        );

        $info->setDemandFee(0.01);
        $info->setSupplyFee(0.01);
        $info->setStatistics(new InfoStatistics(1, 1, 1));

        return [
            'address' => $faker->randomElement($addresses),
            'host' => $host,
            'last_broadcast' => new DateTime(),
            'created_at' => new DateTime(),
            'failed_connection' => 0,
            'info' => $info,
        ];
    }
);
