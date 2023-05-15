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

namespace Adshares\Adserver\Http\Response;

use Adshares\Common\Domain\ValueObject\AccountId;
use Adshares\Common\Domain\ValueObject\Email;
use Adshares\Common\Domain\ValueObject\EmptyAccountId;
use Adshares\Common\Domain\ValueObject\SecureUrl;
use Adshares\Common\Domain\ValueObject\Url;
use Adshares\Config\AppMode;
use Adshares\Supply\Application\Dto\Info;
use Adshares\Supply\Application\Dto\InfoStatistics;
use Illuminate\Contracts\Support\Arrayable;

final class InfoResponse implements Arrayable
{
    public const ADSHARES_MODULE_NAME = 'adserver';

    public function __construct(private readonly Info $info)
    {
    }

    public function updateWithDemandFee(float $fee): void
    {
        $this->info->setDemandFee($fee);
    }

    public function updateWithSupplyFee(float $fee): void
    {
        $this->info->setSupplyFee($fee);
    }

    public function updateWithStatistics(InfoStatistics $statistics): void
    {
        $this->info->setStatistics($statistics);
    }

    public function toArray(): array
    {
        return $this->info->toArray();
    }

    public static function defaults(): self
    {
        return new self(
            new Info(
                self::ADSHARES_MODULE_NAME,
                config('app.adserver_name'),
                config('app.version'),
                new SecureUrl(config('app.url')),
                new Url(config('app.adpanel_url')),
                new Url(config('app.landing_url')),
                new SecureUrl(config('app.url') . route('privacy-url', [], false)),
                new SecureUrl(config('app.url') . route('terms-url', [], false)),
                new SecureUrl(config('app.url') . route('demand-inventory', [], false)),
                null !== config('app.adshares_address')
                    ? new AccountId(config('app.adshares_address')) : new EmptyAccountId(),
                new Email(config('app.support_email')),
                [Info::CAPABILITY_ADVERTISER, Info::CAPABILITY_PUBLISHER],
                config('app.registration_mode'),
                AppMode::getAppMode(),
                config('app.ads_txt_domain'),
                config('app.ads_txt_check_demand_enabled'),
            )
        );
    }
}
