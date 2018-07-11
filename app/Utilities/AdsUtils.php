<?php

namespace Adshares\Adserver\Utilities;

final class AdsUtils
{
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
