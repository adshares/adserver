<?php

namespace Adshares\Adserver\Models\Traits;

use Adshares\Adserver\Utilities\AdsUtils;

/**
 * binhex columns.
 */
trait TransactionId
{
    public function transactionIdMutator($key, $value)
    {
        $this->attributes[$key] = null !== $value ? hex2bin(AdsUtils::decodeTransactionId($value)) : null;
    }

    public function transactionIdAccessor($value)
    {
        return null === $value ? null : AdsUtils::encodeTransactionId(bin2hex($value));
    }
}
