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

namespace Database\Factories;

use Adshares\Common\Domain\ValueObject\AccountId;
use Adshares\Common\Domain\ValueObject\Email;
use Adshares\Common\Domain\ValueObject\Url;
use Adshares\Config\AppMode;
use Adshares\Config\RegistrationMode;
use Adshares\Supply\Application\Dto\Info;
use Adshares\Supply\Application\Dto\InfoStatistics;
use Adshares\Supply\Domain\ValueObject\HostStatus;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

class NetworkHostFactory extends Factory
{
    public function definition(): array
    {
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

        $domain = parse_url($this->faker->url, PHP_URL_HOST);
        $host = 'https://' . $domain;
        $info = new Info(
            'adserver',
            $this->faker->domainWord,
            '0.1',
            new Url($host),
            new Url($host . ':4200'),
            new Url($host),
            new Url($host . '/policies/privacy.html'),
            new Url($host . '/policies/terms.html'),
            new Url($host . '/adshares/inventory/list'),
            new AccountId('0001-00000004-DBEB'),
            new Email($this->faker->companyEmail),
            [Info::CAPABILITY_PUBLISHER, Info::CAPABILITY_ADVERTISER],
            RegistrationMode::PUBLIC,
            AppMode::OPERATIONAL,
            $domain,
            false,
        );

        $info->setDemandFee(0.01);
        $info->setSupplyFee(0.01);
        $info->setStatistics(new InfoStatistics(1, 1, 1));

        return [
            'address' => $this->faker->randomElement($addresses)->toString(),
            'host' => $host,
            'last_broadcast' => new DateTimeImmutable(),
            'created_at' => new DateTimeImmutable(),
            'failed_connection' => 0,
            'info' => $info,
            'info_url' => $info->getServerUrl() . '/info.json',
            'status' => HostStatus::Operational,
        ];
    }
}
