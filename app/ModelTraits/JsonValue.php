<?php

namespace Adshares\Adserver\ModelTraits;

/**
 * binhex columns
 */
trait JsonValue
{
    public function jsonValueMutator($key, $value)
    {
        $this->attributes[$key] = $value !== null ? json_encode($value) : null;
    }

    public function jsonValueAccessor($value)
    {
        return $value === null ? null : json_decode($value);
    }
}
