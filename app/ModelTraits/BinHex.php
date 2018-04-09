<?php
namespace App\ModelTraits;

/**
 * binhex columns
 */
trait BinHex
{
    public function binHexMutator($value)
    {
        return ($value !== null) ? hex2bin($value) : null;
    }

    public function binHexAccessor($value)
    {
        return $value === null ? null : strtolower(bin2hex($value));
    }
}
