<?php

namespace Adshares\Adserver\Models\Traits;

/**
 * binhex columns
 */
trait BinHex
{
    public function binHexMutator($key, $value)
    {
        $this->attributes[$key] = $value !== null ? hex2bin($value) : null;
    }

    public function binHexAccessor($value)
    {
        return $value === null ? null : strtolower(bin2hex($value));
    }
}
