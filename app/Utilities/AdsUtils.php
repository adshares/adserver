<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License,
 * or (at your option) any later version.
 *
 * AdServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdServer.  If not, see <https://www.gnu.org/licenses/>
 */

namespace Adshares\Adserver\Utilities;

final class AdsUtils
{
    /**
     * Minimum transfer fee `TXS_MIN_FEE`
     */
    const TXS_MIN_FEE = 10000;
    /**
     * Local transfer coefficient `TXS_PUT_FEE`
     */
    const TXS_LOCAL_FEE = 0.0005;
    /**
     * Remote transfer coefficient `TXS_LNG_FEE`
     */
    const TXS_REMOTE_FEE = 0.0005;

    /**
     * Calculates transfer fee.
     * @param string $addressFrom sender address
     * @param string $addressTo recipient address
     * @param int $amount amount
     * @return int transfer fee
     */
    public static function calculateFee(string $addressFrom, string $addressTo, int $amount)
    {
        $fee = ceil($amount * self::TXS_LOCAL_FEE);

        if (0 !== substr_compare($addressFrom, $addressTo, 0, 4)) {
            // different nodes
            $fee += ceil($amount * self::TXS_REMOTE_FEE);
        }
        return intval(max($fee, self::TXS_MIN_FEE));
    }

    public static function encodeTxId($binAddress)
    {
        $binAddress = strtoupper($binAddress);

        return sprintf('%s%s%s', substr($binAddress, 0, 4), substr($binAddress, 4, 8), substr($binAddress, 12, 4));
    }

    public static function decodeTxId($address)
    {
        $address = preg_replace('/[^0-9A-F]+/', '', strtoupper($address));

        if (!preg_match('/[0-9A-F]{16}/', $address)) {
            return null;
        }

        return $address;
    }

    public static function normalizeAddress($address)
    {
        $x = preg_replace('/[^0-9A-FX]+/', '', strtoupper($address));
        if (16 != strlen($x)) {
            throw new \RuntimeException('Invalid adshares address');
        }

        return sprintf('%s-%s-%s', substr($x, 0, 4), substr($x, 4, 8), substr($x, 12, 4));
    }

    public static function normalizeTxid($txid)
    {
        $x = preg_replace('/[^0-9A-F]+/', '', strtoupper($txid));
        if (16 != strlen($x)) {
            throw new \RuntimeException('Invalid adshares address');
        }

        return sprintf('%s:%s:%s', substr($x, 0, 4), substr($x, 4, 8), substr($x, 12, 4));
    }
}
