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

namespace Adshares\Adserver\Utilities;

final class AdsUtils
{
    /**
     * Minimum transfer fee `TXS_MIN_FEE`
     */
    public const TXS_MIN_FEE = 10000;

    /**
     * Local transfer coefficient `TXS_PUT_FEE`
     */
    public const TXS_LOCAL_FEE = 0.0005;

    public const TXS_LOCAL_FEE_DIVISOR = 2000;

    /**
     * Remote transfer coefficient `TXS_LNG_FEE`
     */
    public const TXS_REMOTE_FEE = 0.0005;

    public const TXS_REMOTE_FEE_DIVISOR = 2000;

    /**
     * Calculates transfer amount basing on total (amount + fee).
     *
     * @param string $addressFrom sender address
     * @param string $addressTo recipient address
     * @param int $total total
     *
     * @return int transfer amount
     */
    public static function calculateAmount(string $addressFrom, string $addressTo, int $total): int
    {
        if ($total <= self::TXS_MIN_FEE) {
            return 0;
        }

        if (AdsUtils::calculateFee($addressFrom, $addressTo, $total - self::TXS_MIN_FEE) === self::TXS_MIN_FEE) {
            return $total - self::TXS_MIN_FEE;
        }

        $isSameNode = 0 === substr_compare($addressFrom, $addressTo, 0, 4);

        $coefficient = $isSameNode ? self::TXS_LOCAL_FEE : self::TXS_LOCAL_FEE + self::TXS_REMOTE_FEE;

        $amount = intval(floor($total / (1 + $coefficient)));

        do {
            $amount++;
        } while ($total - $amount - AdsUtils::calculateFee($addressFrom, $addressTo, $amount) >= 0);

        $amount--;

        return $amount;
    }

    /**
     * Calculates transfer fee.
     *
     * @param string $addressFrom sender address
     * @param string $addressTo recipient address
     * @param int $amount amount
     *
     * @return int transfer fee
     */
    public static function calculateFee(string $addressFrom, string $addressTo, int $amount): int
    {
        $fee = intdiv($amount, self::TXS_LOCAL_FEE_DIVISOR);

        if (0 !== substr_compare($addressFrom, $addressTo, 0, 4)) {
            // different nodes
            $fee += intdiv($amount, self::TXS_REMOTE_FEE_DIVISOR);
        }

        return intval(max($fee, self::TXS_MIN_FEE));
    }

    public static function encodeTxId($hexTxId): string
    {
        $hexTxId = strtoupper($hexTxId);

        return sprintf(
            '%s:%s:%s',
            substr($hexTxId, 0, 4),
            substr($hexTxId, 4, 8),
            substr($hexTxId, 12, 4)
        );
    }

    public static function decodeTxId($txId): ?string
    {
        $txId = preg_replace('/[^0-9A-F]+/', '', strtoupper($txId));

        if (!preg_match('/[0-9A-F]{16}/', $txId)) {
            return null;
        }

        return $txId;
    }

    public static function decodeAddress($address): ?string
    {
        $address = preg_replace('/[^0-9A-F]+/', '', strtoupper($address));

        if (!preg_match('/[0-9A-F]{16}/', $address)) {
            return null;
        }

        return substr($address, 0, 12);
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
            throw new \RuntimeException('Invalid adshares transaction');
        }

        return sprintf('%s:%s:%s', substr($x, 0, 4), substr($x, 4, 8), substr($x, 12, 4));
    }
}
