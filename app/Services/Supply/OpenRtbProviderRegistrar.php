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

namespace Adshares\Adserver\Services\Supply;

use Adshares\Adserver\Models\NetworkHost;
use Adshares\Common\Domain\ValueObject\AccountId;
use Adshares\Common\Domain\ValueObject\Url;
use Adshares\Config\AppMode;
use Adshares\Config\RegistrationMode;
use Adshares\Supply\Application\Dto\Info;
use DateTimeImmutable;
use Illuminate\Support\Facades\Log;

class OpenRtbProviderRegistrar
{
    public function registerAsNetworkHost(): bool
    {
        if (
            null !== ($accountAddress = config('app.open_rtb_provider_account_address'))
            && null !== ($url = config('app.open_rtb_provider_url'))
        ) {
            $info = new Info(
                'openrtb',
                'OpenRTB Provider',
                '0.1.0',
                new Url($url),
                new Url($url),
                new Url($url),
                new Url($url . '/policies/privacy.html'),
                new Url($url . '/policies/terms.html'),
                new Url($url . '/adshares/inventory/list'),
                new AccountId($accountAddress),
                null,
                [Info::CAPABILITY_ADVERTISER],
                RegistrationMode::PRIVATE,
                AppMode::OPERATIONAL
            );
            $host = NetworkHost::registerHost($accountAddress, $url, $info, new DateTimeImmutable());
            Log::debug(sprintf('Stored %s as #%d', $url, $host->id));
            return true;
        }
        return false;
    }
}
