<?php

namespace Adshares\Adserver\Models\Traits;

use Adshares\Adserver\Models\Contracts\Camelizable;
use Adshares\Adserver\Models\Customizations\CollectionToArrayCamelize;

/**
 * Fix Hash key camelized.
 */
trait ToArrayCamelize
{
    /**
     * Camelize array indexes.
     *
     * @param array $array
     *
     * @return array
     */
    protected static function arrayCamelize($array)
    {
        $r = [];
        foreach ($array as $k => $v) {
            $r[self::snakeToCamel($k)] = $v;
        }

        return $r;
    }

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

    /**
     * Get the model's relationships in array form.
     *
     * @return array
     */
    public function relationsToArrayCamelize()
    {
        $attributes = [];

        foreach ($this->getArrayableRelations() as $key => $value) {
            if ($value instanceof Camelizable) {
                $relation = $value->toArrayCamelize();
            } elseif ($value instanceof Arrayable) {
                $relation = $value->toArray();
            } elseif (is_null($value)) {
                $relation = $value;
            }
            if (isset($relation) || is_null($value)) {
                $attributes[self::snakeToCamel($key)] = $relation;
            }

            unset($relation);
        }

        return $attributes;
    }

    protected static function snakeToCamel($string, $ucfirst = false)
    {
        $str = str_replace(' ', '', ucwords(str_replace('_', ' ', $string)));

        return $ucfirst ? $str : lcfirst($str);
    }

    /**
     * Get the instance as an array wth camelized indexes.
     *
     * @return array
     */
    public function toArrayCamelize()
    {
        if (method_exists($this, 'toArrayProcessTraitAttributes')) {
            return array_merge(
                self::arrayCamelize($this->toArrayProcessTraitAttributes()),
                $this->relationsToArrayCamelize()
            );
        }

        return array_merge(self::arrayCamelize($this->toArray()), $this->relationsToArrayCamelize());
    }
}
