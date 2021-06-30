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

namespace Adshares\Adserver\Models\Traits;

trait AccountAddress
{
    public function accountAddressMutator($key, $value)
    {
        if ($value === null) {
            $this->attributes[$key] = $value;
        }

        $binAddress = $this->decodeAddress($value);

        $this->attributes[$key] = hex2bin($binAddress);
    }

    private function decodeAddress($address)
    {
        $address = preg_replace('/[^0-9A-F]+/', '', strtoupper($address));

        if (!preg_match('/[0-9A-F]{16}/', $address)) {
            throw new \InvalidArgumentException("Incorrect account address $address");
        }

        return substr($address, 0, 12);
    }

    public function accountAddressAccessor($value)
    {
        if ($value === null) {
            return $value;
        }

        return $this->encodeAddress(bin2hex($value));
    }

    private function encodeAddress($binAddress)
    {
        $checksum = self::crc16($binAddress);

        return strtoupper(sprintf("%s-%s-%s", substr($binAddress, 0, 4), substr($binAddress, 4, 8), $checksum));
    }

    private function crc16($hexChars)
    {
        $chars = hex2bin($hexChars);
        $crc = 0x1D0F;

        for ($i = 0; $i < strlen($chars); $i++) {
            $x = ($crc >> 8) ^ ord($chars[$i]);
            $x ^= $x >> 4;
            $crc = (($crc << 8) ^ (($x << 12)) ^ (($x << 5)) ^ ($x)) & 0xFFFF;
        }

        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }
}
