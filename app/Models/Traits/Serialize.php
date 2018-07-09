<?php

namespace Adshares\Adserver\Models\Traits;

/**
 *  store serialized data.
 */
trait Serialize
{
    public function serializeMutator($key, $value)
    {
        $this->attributes[$key] = null !== $value ? serialize($value) : null;
    }

    public function serializeAccessor($value)
    {
        return null === $value ? null : unserialize($value);
    }
}
