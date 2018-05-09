<?php



namespace Adshares\Adserver\ModelTraits;

/**
 * binhex columns
 */
trait TransactionId
{
    // TODO: smell review

    public function encodeTransactionId($binAddress)
    {
        $binAddress = strtoupper($binAddress);
        return sprintf("%s%s%s", substr($binAddress, 0, 4), substr($binAddress, 4, 8), substr($binAddress, 12, 4));
    }

    public function decodeTransactionId($address)
    {
        $address = preg_replace('/[^0-9A-F]+/', '', strtoupper($address));

        if (! preg_match('/[0-9A-F]{16}/', $address)) {
            return null;
        }

        return $address;
    }

    public function transactionIdMutator($key, $value)
    {
        $this->attributes[$key] = $value !== null ? hex2bin($this->decodeTransactionId($value)) : null;
    }

    public function transactionIdAccessor($value)
    {
        return $value === null ? null : $this->encodeTransactionId(bin2hex($value));
    }
}
