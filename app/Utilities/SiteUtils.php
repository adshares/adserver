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

namespace Adshares\Adserver\Utilities;

class SiteUtils
{
    public static function extractNameFromCryptovoxelsDomain(string $domain): string
    {
        if (1 !== preg_match('/^scene-\d+.cryptovoxels.com$/i', $domain)) {
            return $domain;
        }

        $prefixLength = strlen('scene-');
        $suffixLength = strlen('.cryptovoxels.com');
        return sprintf(
            'Cryptovoxels %s',
            substr($domain, $prefixLength, strlen($domain) - $prefixLength - $suffixLength)
        );
    }

    public static function extractNameFromDecentralandDomain(string $domain): string
    {
        if (1 !== preg_match('/^scene-[n]?\d+-[n]?\d+.decentraland.org$/i', $domain)) {
            return $domain;
        }

        if ('scene-0-0.decentraland.org' === $domain) {
            return 'DCL Builder';
        }

        $prefixLength = strlen('scene-');
        $suffixLength = strlen('.decentraland.org');
        return sprintf(
            'Decentraland (%s)',
            str_replace(
                'n',
                '-',
                str_replace(
                    '-',
                    ', ',
                    strtolower(
                        substr($domain, $prefixLength, strlen($domain) - $prefixLength - $suffixLength)
                    )
                )
            )
        );
    }
}
