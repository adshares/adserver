<?php

namespace Adshares\Adserver\ModelTraits;

/**
 *  store money balance
 */
trait Money
{
    public function moneyMutator($key, $value)
    {
        $this->attributes[$key] = $value !== null ? $value : null;
    }

    public function moneyAccessor($value)
    {
        return $value === null ? null : $value;
    }

    // TODO: this is just tmp mock, should be processed to Money class (Currency)
}
