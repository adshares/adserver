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

namespace Adshares\Adserver\Services\Cdn;

use RuntimeException;

final class CdnProviderFactory
{
    public const SKYNET_PROVIDER = 'skynet';

    public static function getProvider(?string $name = null): ?CdnProvider
    {
        $name = $name ?? config('app.cdn_provider');
        if (empty($name)) {
            return null;
        }

        switch ($name) {
            case self::SKYNET_PROVIDER:
                return new SkynetCdn(config('app.skynet_api_url'), config('app.skynet_api_key'), config('app.skynet_cdn_url'));
            default:
                throw new RuntimeException(sprintf('Unknown CDN provider "%s"', $name));
        }
    }
}
