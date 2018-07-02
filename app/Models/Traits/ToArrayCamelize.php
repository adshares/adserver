<?php

namespace Adshares\Adserver\Models\Traits;

use Adshares\Adserver\Models\Customizations\CollectionToArrayCamelize;

/**
 * Fix Hash key camelized.
 */
trait ToArrayCamelize
{
    /**
     * Create a new Eloquent Collection instance.
     *
     * @param array $models
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function newCollection(array $models = [])
    {
        return new CollectionToArrayCamelize($models);
    }

    protected static function snakeToCamel($string, $ucfirst = false)
    {
        $str = str_replace(' ', '', ucwords(str_replace('_', ' ', $string)));

        return $ucfirst ? $str : lcfirst($str);
    }

    public function toArrayCamelize()
    {
        $r = [];
        foreach ($this->toArray() as $k => $v) {
            $r[self::snakeToCamel($k)] = $v;
        }

        return $r;
    }
}
