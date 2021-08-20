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

namespace Adshares\Adserver\Utilities;

use DateTimeInterface;

final class InvoiceUtils
{
    public const EU_COUNTRIES = [
        'AT', //Austria
        'BE', //Belgium
        'BG', //Bulgaria
        'CY', //Cyprus
        'CZ', //Czech Republic
        'DE', //Germany
        'DK', //Denmark
        'EE', //Estonia
        'GR', //Greece | EL
        'ES', //Spain
        'FI', //Finland
        'FR', //France
        'HR', //Croatia
        'HU', //Hungary
        'IE', //Ireland
        'IT', //Italy
        'LT', //Lithuania
        'LU', //Luxembourg
        'LV', //Latvia
        'MT', //Malta
        'NL', //The Netherlands
        'PL', //Poland
        'PT', //Portugal
        'RO', //Romania
        'SE', //Sweden
        'SI', //Slovenia
        'SK', //Slovakia
        'XI', //Northern Ireland
    ];

    public const VAT_RATES = [
        '23%' => 0.23,
        'np.' => 0,
        'np. EU' => 0,
    ];

    public static function getVatRate(string $country, bool $euVat = false): array
    {
        $code = 'np.';
        if ($country === 'PL') {
            $code = '23%';
        } elseif (in_array($country, self::EU_COUNTRIES)) {
            $code = $euVat ? 'np. EU' : '23%';
        }
        return [$code => self::VAT_RATES[$code]];
    }

    public static function formatNumber(
        string $format,
        int $sequence,
        DateTimeInterface $date,
        string $adserverId = ''
    ): string {
        $number = $format;
        $number = str_replace('\\\\', '#-=-#', $number);
        $number = self::replaceKey($number, 'N', (string)$sequence);
        $number = self::replaceKey($number, 'A', $adserverId);
        $number = self::replaceKey($number, 'M', $date->format('m'));
        $number = self::replaceKey($number, 'Y', $date->format('Y'), false);
        return str_replace('#-=-#', '\\', $number);
    }

    private static function replaceKey(string $text, string $key, string $value, bool $trimEnd = true): string
    {
        if (preg_match("/(^|[^\\\\])($key+)/", $text, $matches)) {
            $count = strlen($matches[2]);
            $value = $trimEnd ? substr($value, 0, $count) : substr($value, -$count);
            $text = preg_replace(
                "/(^|[^\\\\])($key+)/",
                sprintf('${1}%0' . $count . 's', $value),
                $text
            );
            $text = str_replace('\\' . $key, $key, $text);
        }
        return $text;
    }
}
