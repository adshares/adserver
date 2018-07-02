<?php

namespace Adshares\Adserver\Models\Customizations;

use Illuminate\Database\Eloquent\Collection;

/**
 * Fix Hash key camelized return from collection.
 */
class CollectionToArrayCamelize extends Collection
{
    /**
     * Get the collection of items as a plain array with key camel fixed.
     *
     * @return array
     */
    public function toArrayCamelize()
    {
        return array_map(function ($value) {
            return $value->toArrayCamelize();
        }, $this->items);
    }
}
