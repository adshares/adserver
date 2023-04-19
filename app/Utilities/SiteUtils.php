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

namespace Adshares\Adserver\Utilities;

class SiteUtils
{
    public static function extractNameFromCryptovoxelsDomain(string $domain): string
    {
        $prefixLength = strlen('scene-');
        $suffixLength = strlen('.cryptovoxels.com');
        return sprintf(
            'Cryptovoxels %s',
            substr($domain, $prefixLength, strlen($domain) - $prefixLength - $suffixLength)
        );
    }

    public static function extractNameFromDecentralandDomain(string $domain): string
    {
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

    public static function extractNameFromPolkaCityDomain(string $domain): string
    {
        return sprintf('PolkaCity (%s)', explode('.', $domain)[0]);
    }

    public static function isValidCryptovoxelsUrl(string $url): bool
    {
        return 1 === preg_match(
            '~^https://scene-\d+.cryptovoxels.com$~i',
            $url,
        );
    }

    public static function isValidDecentralandUrl(string $url): bool
    {
        return 1 === preg_match('~^https://scene-n?\d+-n?\d+.decentraland.org$~i', $url);
    }

    public static function isValidPolkaCityUrl(string $url): bool
    {
        return 1 === preg_match('~^https://[a-z0-9-]+.polkacity.io$~i', $url);
    }
}
