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
}
