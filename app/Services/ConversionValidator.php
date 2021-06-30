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

namespace Adshares\Adserver\Services;

use Adshares\Adserver\Http\Utils;
use Adshares\Common\Exception\RuntimeException;
use Illuminate\Support\Facades\Cache;

class ConversionValidator
{
    private const CACHE_ITEM_TTL_IN_MINUTES = 5;

    public function validateSignature(
        string $signature,
        string $conversionDefinitionUuid,
        string $nonce,
        int $timestampCreated,
        string $value,
        string $secret,
        string $caseId = ''
    ): bool {
        $timestampCurrent = time();

        if ($timestampCreated > $timestampCurrent) {
            return false;
        }

        if ($timestampCurrent - $timestampCreated > self::CACHE_ITEM_TTL_IN_MINUTES * 60) {
            return false;
        }

        $cacheKey = md5($nonce);

        if (Cache::has($cacheKey)) {
            throw new RuntimeException('Previously used nonce detected');
        }

        Cache::put($cacheKey, 1, self::CACHE_ITEM_TTL_IN_MINUTES);

        $rawNonce = Utils::urlSafeBase64Decode($nonce);
        $expected = Utils::urlSafeBase64Encode(
            hash(
                'sha256',
                $conversionDefinitionUuid . $rawNonce . $timestampCreated . $value . $caseId . $secret,
                true
            )
        );

        return hash_equals($expected, $signature);
    }
}
