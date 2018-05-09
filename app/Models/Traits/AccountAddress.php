<?php

namespace Adshares\Adserver\Models\Traits;

/**
 * adresses columns
 */
trait AccountAddress
{
    //  TODO: this code requires additional review in relation to half of it not being used (?)

    private function crc16($hexChars)
    {
        $chars = hex2bin($hexChars);
        $crc = 0x1D0F;

        for ($i = 0; $i < strlen($chars); $i ++) {
            $x = ($crc >> 8) ^ ord($chars[$i]);
            $x ^= $x >> 4;
            $crc = (($crc << 8) ^ (($x << 12)) ^ (($x << 5)) ^ ($x)) & 0xFFFF;
        }
        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }

    public function encodeAddress($binAddress)
    {
        $checksum = $this->crc16($binAddress);
        return strtoupper(sprintf("%s-%s-%s", substr($binAddress, 0, 4), substr($binAddress, 4, 8), $checksum));
    }

    // TODO: unused ? review
    public function decodeAddress($address)
    {
        $address = preg_replace('/[^0-9A-F]+/', '', strtoupper($address));

        if (! preg_match('/[0-9A-F]{16}/', $address)) {
            return null;
        }

        $checksum = substr($address, 12, 4);
        $binAddress = substr($address, 0, 12);

        if ($this->crc16($binAddress) != $checksum) {
            return false;
        }

        return $binAddress;
    }

    public function accountAddressMutator($key, $value)
    {
        if ($value === null) {
            $this->attributes[$key] = $value;
        }
        $address = preg_replace('/[^0-9A-F]+/', '', strtoupper($value));

        if (! preg_match('/[0-9A-F]{16}/', $address)) {
            throw new \InvalidArgumentException("Incorrect account address $address");
        }
        $checksum = substr($address, 12, 4);
        $binAddress = substr($address, 0, 12);

//         if ($this->crc16($binAddress) != $checksum) {
//             throw new \InvalidArgumentException("Incorrect account crc");
//         }
//         echo $address;exit;
        $this->attributes[$key] = hex2bin($binAddress);
    }

    /**
     * {@inheritdoc}
     */
    public function accountAddressAccessor($value)
    {
        if ($value === null) {
            return $value;
        }
        return $this->encodeAddress(bin2hex($value));
    }
}
