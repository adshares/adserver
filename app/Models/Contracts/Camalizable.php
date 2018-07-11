<?php

namespace Adshares\Adserver\Models\Contracts;

interface Camelizable
{
    /**
     * Get the instance as an array wth camelized indexes.
     *
     * @return array
     */
    public function toArrayCamelize();
}
